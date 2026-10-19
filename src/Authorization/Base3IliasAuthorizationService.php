<?php declare(strict_types=1);

namespace Base3Ilias\Authorization;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Api\IClassMap;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Api\IFeature;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Logger\Api\ILogger;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Role;
use Base3\Usermanager\UserCriterionEvaluator;
use InvalidArgumentException;
use ilRbacSystem;
use Throwable;

/** Native ILIAS authorization owner; no custom repository-tree or effective-role computation. */
final class Base3IliasAuthorizationService implements IAuthorizationService {

	public const AUTHORITY = 'ilias';

	/** @var array<string,IFeature|null>|null */
	private ?array $featurePermissions = null;

	public function __construct(
		private readonly IAccesscontrol $accesscontrol,
		private readonly IUsermanager $usermanager,
		private readonly IClassMap $classMap,
		private readonly Base3IliasFeatureConfiguration $featureConfiguration,
		private readonly UserCriterionEvaluator $criterionEvaluator,
		private readonly ilRbacSystem $rbacSystem,
		private readonly ILogger $logger
	) {}

	public function getDefaultAuthority(): string { return self::AUTHORITY; }

	public function decide(AuthorizationRequest $request): AuthorizationDecision {
		if ($request->authority !== self::AUTHORITY) return AuthorizationDecision::indeterminate('unknown_authority');
		if ($request->onBehalfOf !== null) return AuthorizationDecision::deny('delegation_not_supported');
		try {
			$userId = $this->resolveUserId($request);
			if ($userId === null) return AuthorizationDecision::deny('unknown_subject');

			$scope = strtolower(trim((string)$request->permission->scope));
			$operation = strtolower(trim((string)$request->permission->permission));

			if ($scope === 'feature') return $this->decideFeature($request, $userId);
			if ($scope === 'system' && $operation === 'admin' && $request->resource === null) {
				return $this->isSystemAdministrator($userId)
					? AuthorizationDecision::allow('native_administrator')
					: AuthorizationDecision::deny('not_administrator');
			}
			if ($scope !== 'ilias') return AuthorizationDecision::deny('unsupported_scope');
			if ($request->resource === null || $request->resource->type !== 'ref_id') {
				return AuthorizationDecision::deny('ref_id_required');
			}
			$refId = filter_var($request->resource->id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
			if ($refId === false) return AuthorizationDecision::deny('invalid_ref_id');

			$operations = array_map('trim', explode(',', $operation));
			foreach ($operations as $nativeOperation) {
				if (!preg_match('/^[a-z][a-z0-9_]*$/D', $nativeOperation)) {
					return AuthorizationDecision::deny('invalid_native_operation');
				}
			}
			$operation = implode(',', $operations);
			$objectType = '';
			if (in_array('create', $operations, true)) {
				$type = $request->parameters['object_type'] ?? null;
				if (!is_string($type) || !preg_match('/^[a-z][a-z0-9_]*$/D', $type)) {
					return AuthorizationDecision::deny('native_create_type_required');
				}
				$objectType = $type;
			}

			// ILIAS owns repository hierarchy, effective roles, owner rules and permission cache.
			// Native `create` additionally requires the type of the object being created.
			$allowed = $this->rbacSystem->checkAccessOfUser($userId, $operation, $refId, $objectType);
			return $allowed ? AuthorizationDecision::allow('native_rbac') : AuthorizationDecision::deny('native_rbac');
		} catch (Throwable $error) {
			$this->logger->error('ILIAS authorization could not be evaluated.', ['exception_class' => get_class($error)]);
			return AuthorizationDecision::indeterminate('native_authority_error');
		}
	}

	public function decideMany(array $requests): array {
		$preload = [];
		foreach ($requests as $request) {
			if (!$request instanceof AuthorizationRequest) throw new InvalidArgumentException('Expected AuthorizationRequest.');
			if ($request->authority !== self::AUTHORITY || $request->permission->scope !== 'ilias' || $request->resource?->type !== 'ref_id') continue;
			$userId = $this->resolveUserId($request);
			$refId = filter_var($request->resource->id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
			if ($userId !== null && $refId !== false) $preload[$userId][$refId] = $refId;
		}
		foreach ($preload as $userId => $refIds) {
			try {
				// Use the indexed native ILIAS RBAC preload instead of N+1 hierarchy traversals.
				$this->rbacSystem->preloadRbacPaCache(array_values($refIds), (int)$userId);
			} catch (Throwable $error) {
				$this->logger->error('ILIAS RBAC preloading failed.', ['exception_class' => get_class($error)]);
				// Individual calls still run natively and fail closed when an error persists.
			}
		}
		$result = [];
		foreach ($requests as $key => $request) $result[$key] = $this->decide($request);
		return $result;
	}

	private function resolveUserId(AuthorizationRequest $request): ?int {
		if ($request->subject !== null) {
			if ($request->subject->authority !== self::AUTHORITY || $request->subject->kind !== 'user') return null;
			$value = $request->subject->id;
		} else {
			$value = $this->accesscontrol->getUserId();
		}
		$id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		return $id === false || $id === null ? null : $id;
	}

	private function isSystemAdministrator(int $userId): bool {
		if ($this->isCurrentUser($userId)) return $this->usermanager->hasRole(Role::named('admin'));
		$user = $this->usermanager->getUserById($userId);
		if ($user === null) return false;
		foreach ($user->roles as $role) {
			if ($role instanceof Role && $role->name === 'admin') return true;
		}
		return false;
	}

	private function isCurrentUser(int $userId): bool {
		return (string)$userId === (string)$this->accesscontrol->getUserId();
	}

	private function decideFeature(AuthorizationRequest $request, int $userId): AuthorizationDecision {
		if ($request->resource !== null) return AuthorizationDecision::deny('feature_is_global');
		if (!$this->isCurrentUser($userId)) return AuthorizationDecision::deny('feature_subject_not_current');
		$key = (string)$request->permission->scope . '/' . (string)$request->permission->permission;
		$catalog = $this->featureCatalog();
		if (!array_key_exists($key, $catalog)) return AuthorizationDecision::deny('unknown_feature_permission');
		$feature = $catalog[$key];
		if (!$feature instanceof IFeature) return AuthorizationDecision::indeterminate('duplicate_feature_permission');
		$groups = array_map(static fn($group): string => (string)$group->name, $this->usermanager->getGroups());
		$audience = $this->featureConfiguration->getAudience($feature);
		$allowed = $this->criterionEvaluator->matches($this->usermanager->getUser(), $audience, $groups);
		return $allowed ? AuthorizationDecision::allow('base3_feature_audience') : AuthorizationDecision::deny('feature_audience_not_allowed');
	}

	/** @return array<string,IFeature|null> */
	private function featureCatalog(): array {
		if ($this->featurePermissions !== null) return $this->featurePermissions;
		$catalog = [];
		foreach ($this->classMap->getInstancesByInterface(IFeature::class) as $feature) {
			if (!$feature instanceof IFeature) continue;
			foreach ($feature->getPermissions() as $permission) {
				if (!$permission instanceof \Base3\Usermanager\Permission) continue;
				$key = (string)$permission->scope . '/' . (string)$permission->permission;
				if (!array_key_exists($key, $catalog)) $catalog[$key] = $feature;
				else $catalog[$key] = null;
			}
		}
		return $this->featurePermissions = $catalog;
	}

}
