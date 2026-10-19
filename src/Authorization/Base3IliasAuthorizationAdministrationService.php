<?php declare(strict_types=1);

namespace Base3Ilias\Authorization;

use Base3\Authorization\Api\IAuthorizationAdministrationService;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationCapabilities;
use Base3\Authorization\Dto\AuthorizationGrant;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Authorization\Dto\AuthorizationSubject;
use Base3\Authorization\Dto\ResourceReference;
use Base3\Authorization\Dto\SubjectReference;
use Base3\Usermanager\Permission;
use InvalidArgumentException;
use RuntimeException;
use ilObject;
use ilRbacAdmin;
use ilRbacReview;
use ilRbacSystem;
use ilDBInterface;
use Throwable;

/** Native ILIAS RBAC administration; no BASE3 permission tables or synthetic role inheritance. */
final class Base3IliasAuthorizationAdministrationService implements IAuthorizationAdministrationService {

	public function __construct(
		private readonly IAuthorizationService $authorization,
		private readonly ilRbacReview $review,
		private readonly ilRbacAdmin $admin,
		private readonly ilDBInterface $database
	) {}

	public function describe(string $authority, ?ResourceReference $resource = null): AuthorizationCapabilities {
		$this->assertAuthority($authority, $resource);
		$allowed = $resource !== null ? $this->canEdit($resource) : $this->authorization->decide(
			new AuthorizationRequest($authority, Permission::for('system', 'admin'))
		)->isAllowed();
		return new AuthorizationCapabilities(
			$authority, $allowed, $allowed, true, ['role'],
			['Only native ILIAS role/object grants are editable; user and group memberships are managed by ILIAS.',
			 'Native inheritance is not reconstructed by BASE3. Provenance is unknown unless ILIAS supplies it.']
		);
	}

	public function getPermissions(string $authority, ?ResourceReference $resource = null): array {
		$this->assertAuthority($authority, $resource);
		$this->assertCanRead($resource);
		$operations = $this->review->getOperations();
		$allowedIds = $resource !== null ? $this->getValidOperationIds($resource) : null;
		$out = [];
		foreach ($operations as $operation) {
			$id = (int)($operation['ops_id'] ?? 0);
			$name = trim((string)($operation['operation'] ?? ''));
			if ($name === '' || $id <= 0 || ($allowedIds !== null && !isset($allowedIds[$id]))) continue;
			$out[] = Permission::fromArray([
				'id' => $id, 'scope' => 'ilias', 'permission' => $name,
				'label' => $name, 'info' => (string)($operation['description'] ?? ''), 'archive' => 0
			]);
		}
		return $out;
	}

	public function getSubjects(ResourceReference $resource): array {
		$refId = $this->assertResource($resource);
		$this->assertCanRead($resource);
		$subjects = [];
		foreach ($this->review->getParentRoleIds($refId, false) as $data) {
			$id = (int)($data['rol_id'] ?? 0);
			if ($id <= 0) continue;
			$protected = !empty($data['protected']) || (defined('SYSTEM_ROLE_ID') && $id === (int)SYSTEM_ROLE_ID);
			$subjects[] = new AuthorizationSubject(
				new SubjectReference($resource->authority, 'role', $id),
				(string)ilObject::_lookupTitle($id),
				!$protected,
				['protected' => $protected]
			);
		}
		return $subjects;
	}

	public function getGrants(ResourceReference $resource): array {
		$refId = $this->assertResource($resource);
		$this->assertCanRead($resource);
		$catalog = [];
		foreach ($this->getPermissions($resource->authority, $resource) as $permission) {
			$catalog[(int)$permission->id] = $permission;
		}
		$grants = [];
		foreach ($this->getSubjects($resource) as $subject) {
			$roleId = (int)$subject->reference->id;
			$direct = array_fill_keys($this->review->getRoleOperationsOnObject($roleId, $refId), true);
			$effective = $this->review->getActiveOperationsOfRole($refId, $roleId);
			// A directly configured grant must remain visible even if ILIAS currently blocks it.
			// Reading only effective permissions would silently erase such direct grants on save.
			foreach (array_unique(array_merge(array_keys($direct), $effective)) as $operationId) {
				$permission = $catalog[(int)$operationId] ?? null;
				if ($permission === null) continue;
				$isDirect = isset($direct[(int)$operationId]);
				$grants[] = new AuthorizationGrant(
					$subject->reference, $permission, $resource,
					$subject->editable && $isDirect, !$isDirect
				);
			}
		}
		return $grants;
	}

	public function replaceGrants(ResourceReference $resource, SubjectReference $subject, array $permissions): void {
		$refId = $this->assertResource($resource);
		$this->assertCanWrite($resource);
		if ($subject->authority !== $resource->authority || $subject->kind !== 'role' || !is_numeric($subject->id) || (int)$subject->id <= 0) {
			throw new InvalidArgumentException('ILIAS manages role grants with a numeric native role ID only.');
		}
		$roleId = (int)$subject->id;
		$editable = false;
		foreach ($this->getSubjects($resource) as $candidate) {
			if ((int)$candidate->reference->id === $roleId) {
				$editable = $candidate->editable;
				break;
			}
		}
		if (!$editable) throw new RuntimeException('The native role is not editable in this resource.');
		$catalog = [];
		foreach ($this->getPermissions($resource->authority, $resource) as $definition) {
			$catalog[(string)$definition->permission] = (int)$definition->id;
		}
		$ids = [];
		foreach ($permissions as $permission) {
			if (!$permission instanceof Permission || $permission->scope !== 'ilias' || !isset($catalog[(string)$permission->permission])) {
				throw new InvalidArgumentException('Invalid native ILIAS permission for this object type.');
			}
			$ids[] = $catalog[(string)$permission->permission];
		}
		$ids = array_values(array_unique($ids));
		// The native grant operation removes and inserts rbac_pa rows; never attempt it
		// unless the host can guarantee an atomic database transaction.
		if (!$this->database->supportsTransactions()) {
			throw new RuntimeException('Native ILIAS RBAC updates require transactional storage.');
		}
		if (!$this->database->beginTransaction()) {
			throw new RuntimeException('Could not begin an atomic ILIAS RBAC update.');
		}
		try {
			$this->admin->grantPermission($roleId, $ids, $refId);
			if (!$this->database->commit()) {
				throw new RuntimeException('Could not commit the ILIAS RBAC update.');
			}
		} catch (Throwable $error) {
			$this->database->rollback();
			throw $error;
		}
		ilRbacSystem::resetCaches();
	}

	private function assertAuthority(string $authority, ?ResourceReference $resource): void {
		if ($authority !== Base3IliasAuthorizationService::AUTHORITY || ($resource !== null && $resource->authority !== $authority)) {
			throw new InvalidArgumentException('Unknown ILIAS authorization authority.');
		}
		if ($resource !== null) $this->assertResource($resource);
	}

	private function assertResource(ResourceReference $resource): int {
		$this->assertAuthority($resource->authority, null);
		$id = filter_var($resource->id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if ($resource->type !== 'ref_id' || $id === false) throw new InvalidArgumentException('Expected native ILIAS ref_id resource.');
		return $id;
	}

	private function canEdit(ResourceReference $resource): bool {
		return $this->authorization->decide(
			new AuthorizationRequest($resource->authority, Permission::for('ilias', 'edit_permission'), $resource)
		)->isAllowed();
	}

	private function assertCanRead(?ResourceReference $resource): void {
		$allowed = $resource !== null ? $this->canEdit($resource) : $this->authorization->decide(
			new AuthorizationRequest(Base3IliasAuthorizationService::AUTHORITY, Permission::for('system', 'admin'))
		)->isAllowed();
		if (!$allowed) throw new RuntimeException('Not permitted to inspect native ILIAS role grants.');
	}

	private function assertCanWrite(ResourceReference $resource): void {
		if (!$this->canEdit($resource)) throw new RuntimeException('Native edit_permission is required to change role grants.');
	}

	private function getValidOperationIds(ResourceReference $resource): array {
		$refId = $this->assertResource($resource);
		$type = ilObject::_lookupType($refId, true);
		$valid = [];
		foreach (ilRbacReview::_getOperationList($type) as $operation) {
			$id = (int)($operation['ops_id'] ?? 0);
			if ($id > 0) $valid[$id] = true;
		}
		return $valid;
	}
}
