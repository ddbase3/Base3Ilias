<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\ICheck;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Usermanager\UserCriterionEvaluator;
use Base3Ilias\Service\Base3IliasGroupConfiguration;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Group;
use Base3\Usermanager\Permission;
use Base3\Usermanager\Role;
use Base3\Usermanager\User;
use ilObject;
use ilObjUser;

class Base3IliasUsermanager implements IUsermanager, ICheck {

	private ?User $user = null;
	private ?array $groups = null;
	private ?array $roles = null;
	private ?array $permissions = null;
	private ?array $allPermissions = null;

	public function __construct(
		private readonly IAccesscontrol $accesscontrol,
		private readonly \ilRbacReview $rbacreview,
		private readonly UserCriterionEvaluator $criterionEvaluator,
		private readonly Base3IliasGroupConfiguration $groupConfiguration
	) {}


	// Implementation of IUsermanager

	public function getUser() {
		if ($this->user) return $this->user;

		$userId = $this->getCurrentUserId();
		if ($userId <= 0 || $this->isAnonymousUser($userId)) return null;
		if (!$this->userExists($userId)) return null;

		$this->user = $this->createUser($userId, $this->getRoles());

		return $this->user;
	}

	public function getUserById(int|string $id): ?User {
		$userId = $this->normalizeUserId($id);
		if ($userId === null || $this->isAnonymousUser($userId)) return null;
		if (!$this->userExists($userId)) return null;

		if ($this->user instanceof User && (int)$this->user->id === $userId) {
			return $this->user;
		}

		return $this->createUser($userId, $this->getRolesForUserId($userId));
	}

	public function getGroups() {
		if ($this->groups !== null) return $this->groups;
		$user = $this->getUser();
		$groups = [];
		$explicitMatch = false;
		foreach ($this->groupConfiguration->getEntries() as $entry) {
			try {
				$matched = $this->criterionEvaluator->matches($user, $entry['criteria']);
			} catch (\InvalidArgumentException $error) {
				$matched = false;
			}
			if (!$matched) continue;
			$groups[] = $entry['group'];
			if ($entry['editable']) $explicitMatch = true;
		}
		if ($user !== null && !$explicitMatch) {
			$settings = $this->groupConfiguration->get();
			$fallback = trim((string)($settings['default_group'] ?? ''));
			// Privileged groups must always have an explicit criterion, never a fallback assignment.
			if (!in_array($fallback, ['', 'development', 'administration'], true)) {
				foreach ($this->groupConfiguration->getEntries() as $entry) {
					if (!$entry['editable'] || (string)$entry['group']->name !== $fallback) continue;
					$groups[] = $entry['group'];
					break;
				}
			}
		}
		$this->groups = $groups;
		return $groups;
	}


	public function getRoles() {
		if ($this->roles !== null) return $this->roles;

		$userId = $this->getCurrentUserId();
		if ($userId <= 0 || $this->isAnonymousUser($userId) || $this->rbacreview == null) {
			$this->roles = array();
			return $this->roles;
		}

		$this->roles = $this->getRolesForUserId($userId);
		return $this->roles;
	}

	public function getPermissions() {
		if ($this->permissions !== null) return $this->permissions;

		$userId = $this->getCurrentUserId();
		$this->permissions = $this->isAdministrator($userId)
			? $this->getAdministratorPermissions()
			: array();

		return $this->permissions;
	}

	public function hasRole(Role $role): bool {
		$userId = $this->getCurrentUserId();

		if ($userId <= 0 || $this->isAnonymousUser($userId)) return false;

		$wantedId = trim((string)$role->id);
		$wantedName = strtolower(trim((string)$role->name));

		if ($wantedName === 'admin') {
			return $this->isAdministrator($userId);
		}

		foreach ($this->getRoles() as $currentRole) {
			$currentId = trim((string)$currentRole->id);
			$currentName = strtolower(trim((string)$currentRole->name));
			$currentLabel = strtolower(trim((string)$currentRole->label));

			if ($wantedId !== '' && $wantedId === $currentId) return true;
			if ($wantedName !== '' && ($wantedName === $currentName || $wantedName === $currentLabel)) return true;
		}

		return false;
	}

	public function registUser($userid, $password, $data = null) {
		return false;
	}

	public function changePassword($oldpassword, $newpassword) {
		return false;
	}

	public function getAllUsers() {
		$user = $this->getUser();
		if ($user == null) return array();

		return array($user);
	}

	public function getAllGroups() {
		return $this->groupConfiguration->getGroups();
	}



	public function getAllRoles() {
		return $this->getRoles();
	}

	public function getAllPermissions() {
		if ($this->allPermissions !== null) return $this->allPermissions;

		$permissions = $this->getPermissions();

		if ($this->rbacreview != null) {
			foreach ($this->rbacreview->getOperations() as $operation) {
				$name = trim((string)($operation['operation'] ?? ''));
				if ($name === '') continue;

				$permissions[] = Permission::fromArray(array(
					'id' => (string)($operation['ops_id'] ?? ''),
					'scope' => 'ilias',
					'permission' => $name,
					'label' => $name,
					'info' => (string)($operation['description'] ?? 'ILIAS operation. Effective grants depend on a target ref_id.'),
					'archive' => 0
				));
			}
		}

		$this->allPermissions = $this->uniquePermissions($permissions);
		return $this->allPermissions;
	}

	public function assignRoleToUser($userid, Role $role): bool {
		return false;
	}

	public function revokeRoleFromUser($userid, Role $role): bool {
		return false;
	}

	public function assignRoleToGroup($groupid, Role $role): bool {
		return false;
	}

	public function revokeRoleFromGroup($groupid, Role $role): bool {
		return false;
	}

	public function addPermissionToRole(Role $role, Permission $permission): bool {
		return false;
	}

	public function removePermissionFromRole(Role $role, Permission $permission): bool {
		return false;
	}

	// Implementation of ICheck

	public function checkDependencies() {
		return array(
			'accesscontrol' => 'Ok',
			'ilUser' => 'Ok',
			'rbacreview' => 'Ok'
		);
	}

	// Private methods

	private function normalizeUserId(int|string $id): ?int {
		if (!is_numeric($id) || (int)$id <= 0) return null;

		return (int)$id;
	}

	private function createUser(int $userId, array $roles): User {
		$name = ilObjUser::_lookupName($userId);
		$login = ilObjUser::_lookupLogin($userId);
		$fullname = trim(trim((string)($name['title'] ?? '')) . ' ' . trim((string)($name['firstname'] ?? '')) . ' ' . trim((string)($name['lastname'] ?? '')));

		if ($fullname === '') {
			$fullname = $login;
		}

		$user = new User();
		$user->id = (string)$userId;
		$user->userid = $login;
		$user->name = $fullname;
		$user->email = ilObjUser::_lookupEmail($userId);
		$user->lang = ilObjUser::_lookupLanguage($userId);
		$user->role = $this->getCompatibilityRoleName($userId);
		$user->roles = $roles;

		return $user;
	}

	private function getRolesForUserId(int $userId): array {
		if ($userId <= 0 || $this->isAnonymousUser($userId) || $this->rbacreview == null) {
			return array();
		}

		$roleIds = array_map('intval', $this->rbacreview->assignedRoles($userId));
		$globalRoleIds = array_map('intval', $this->rbacreview->assignedGlobalRoles($userId));
		$roleIds = array_values(array_unique($roleIds));
		sort($roleIds);

		$roles = array();

		foreach ($roleIds as $roleId) {
			$roles[] = $this->createRoleFromIliasRoleId($roleId, in_array($roleId, $globalRoleIds, true));
		}

		if ($this->isAdministrator($userId) && !$this->containsRoleName($roles, 'admin')) {
			$roles[] = $this->createAdministratorRole();
		}

		return $roles;
	}

	private function getCurrentUserId(): int {
		$id = $this->accesscontrol->getUserId();
		return is_numeric($id) && (int)$id > 0 ? (int)$id : 0;
	}


	private function userExists(int $userId): bool {
		if ($userId <= 0) return false;

		$name = ilObjUser::_lookupName($userId);
		return ((int)($name['user_id'] ?? 0)) > 0;
	}

	private function isAnonymousUser(int $userId): bool {
		$anonymousUserId = defined('ANONYMOUS_USER_ID') ? (int)ANONYMOUS_USER_ID : 13;
		return $userId === $anonymousUserId;
	}

	private function isAdministrator(int $userId): bool {
		if (
			$userId <= 0
			|| $this->isAnonymousUser($userId)
			|| $this->rbacreview == null
			|| !defined('SYSTEM_ROLE_ID')
		) {
			return false;
		}

		return in_array(
			(int)SYSTEM_ROLE_ID,
			array_map('intval', $this->rbacreview->assignedRoles($userId)),
			true
		);
	}

	private function getCompatibilityRoleName(int $userId): string {
		if ($userId <= 0 || $this->isAnonymousUser($userId)) return 'visit';
		if ($this->isAdministrator($userId)) return 'admin';

		return 'member';
	}

	private function createAdministratorRole(): Role {
		return Role::fromArray(array(
			'id' => 'base3:admin',
			'name' => 'admin',
			'label' => 'Administrator',
			'info' => 'Derived from the native ILIAS system administrator role.',
			'archive' => 0,
			'permissions' => $this->getAdministratorPermissions()
		));
	}

	private function getAdministratorPermissions(): array {
		return array(
			Permission::fromArray(array(
				'scope' => 'system',
				'permission' => 'admin',
				'label' => 'System administration',
				'info' => 'Derived from the native ILIAS system administrator role.',
				'archive' => 0
			))
		);
	}

	private function createRoleFromIliasRoleId(int $roleId, bool $global): Role {
		$title = ilObject::_lookupTitle($roleId);
		$name = $this->normalizeRoleName($title);

		return Role::fromArray(array(
			'id' => (string)$roleId,
			'name' => $name,
			'label' => $title,
			'info' => $global ? 'ILIAS global role.' : 'ILIAS local or linked role.',
			'archive' => 0,
			'permissions' => array()
		));
	}

	private function normalizeRoleName(string $title): string {
		$name = strtolower(trim($title));
		$name = preg_replace('/[^a-z0-9]+/', '_', $name) ?: '';
		$name = trim($name, '_');

		return $name !== '' ? $name : 'role';
	}

	private function containsRoleName(array $roles, string $name): bool {
		$name = strtolower(trim($name));

		foreach ($roles as $role) {
			if (strtolower(trim((string)$role->name)) === $name) return true;
		}

		return false;
	}

	private function uniquePermissions(array $permissions): array {
		$out = array();
		$seen = array();

		foreach ($permissions as $permission) {
			$scope = strtolower(trim((string)$permission->scope));
			$name = strtolower(trim((string)$permission->permission));
			$key = $scope . '/' . $name;

			if ($scope === '' || $name === '' || isset($seen[$key])) continue;

			$seen[$key] = true;
			$out[] = $permission;
		}

		return $out;
	}
}
