<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\ICheck;
use Base3\Core\ServiceLocator;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Group;
use Base3\Usermanager\Permission;
use Base3\Usermanager\Role;
use Base3\Usermanager\User;
use ilObject;
use ilObjUser;
use ilRbacReview;

class Base3IliasUsermanager implements IUsermanager, ICheck {

	private $servicelocator;
	private $accesscontrol;
	private $ilAuthSession;
	private $ilObjUser;
	private $rbacreview;

	private $user;
	private $groups;
	private $roles;
	private $permissions;
	private $allPermissions;

	public function __construct() {
		$this->servicelocator = ServiceLocator::getInstance();
		$this->accesscontrol = $this->servicelocator->get('accesscontrol');
		$this->ilAuthSession = $this->servicelocator->get('ilAuthSession');
		$this->ilObjUser = $this->servicelocator->get('ilUser');
		$this->rbacreview = $this->servicelocator->get('rbacreview');

		if ($this->rbacreview == null) {
			$this->rbacreview = $this->servicelocator->get('ilRbacReview');
		}

		if ($this->rbacreview == null && isset($GLOBALS['DIC']['rbacreview'])) {
			$this->rbacreview = $GLOBALS['DIC']['rbacreview'];
		}
	}

	// Implementation of IUsermanager

	public function getUser() {
		if ($this->user) return $this->user;

		$userId = $this->getCurrentUserId();
		if ($userId <= 0 || $this->isAnonymousUser($userId)) return null;
		if (!$this->userExists($userId)) return null;

		$name = ilObjUser::_lookupName($userId);
		$login = ilObjUser::_lookupLogin($userId);
		$fullname = trim(trim((string)($name['title'] ?? '')) . ' ' . trim((string)($name['firstname'] ?? '')) . ' ' . trim((string)($name['lastname'] ?? '')));

		if ($fullname === '') {
			$fullname = $login;
		}

		$this->user = new User();
		$this->user->id = (string)$userId;
		$this->user->userid = $login;
		$this->user->name = $fullname;
		$this->user->email = ilObjUser::_lookupEmail($userId);
		$this->user->lang = ilObjUser::_lookupLanguage($userId);
		$this->user->role = $this->getCompatibilityRoleName($userId);
		$this->user->roles = $this->getRoles();

		return $this->user;
	}

	public function getGroups() {
		if ($this->groups !== null) return $this->groups;

		$this->groups = array();
		return $this->groups;
	}

	public function getRoles() {
		if ($this->roles !== null) return $this->roles;

		$userId = $this->getCurrentUserId();
		if ($userId <= 0 || $this->isAnonymousUser($userId) || $this->rbacreview == null) {
			$this->roles = array();
			return $this->roles;
		}

		$roleIds = array_map('intval', $this->rbacreview->assignedRoles($userId));
		$globalRoleIds = array_map('intval', $this->rbacreview->assignedGlobalRoles($userId));
		$roleIds = array_values(array_unique($roleIds));
		sort($roleIds);

		$roles = array();

		foreach ($roleIds as $roleId) {
			$role = $this->createRoleFromIliasRoleId($roleId, in_array($roleId, $globalRoleIds, true));
			$roles[] = $role;
		}

		if ($this->isAdministrator($userId) && !$this->containsRoleName($roles, 'admin')) {
			$roles[] = Role::fromArray(array(
				'id' => 'base3:admin',
				'name' => 'admin',
				'label' => 'Administrator',
				'info' => 'Derived from ILIAS administrator privileges.',
				'archive' => 0,
				'permissions' => $this->getPermissions()
			));
		}

		$this->roles = $roles;
		return $this->roles;
	}

	public function getPermissions() {
		if ($this->permissions !== null) return $this->permissions;

		$userId = $this->getCurrentUserId();
		$permissions = array();

		if ($userId > 0 && $this->isAdministrator($userId)) {
			$permissions[] = Permission::fromArray(array(
				'scope' => 'system',
				'permission' => 'admin',
				'label' => 'System administration',
				'info' => 'Derived from ILIAS administrator privileges.',
				'archive' => 0
			));
			$permissions[] = Permission::fromArray(array(
				'scope' => 'entry',
				'permission' => 'admin',
				'label' => 'Entry administration',
				'info' => 'Allows BASE3 entry-admin bypass in embedded ILIAS runtimes.',
				'archive' => 0
			));
		}

		$this->permissions = $permissions;
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

	public function can(Permission $permission): bool {
		$userId = $this->getCurrentUserId();
		if ($userId <= 0 || $this->isAnonymousUser($userId)) return false;

		$scope = strtolower(trim((string)$permission->scope));
		$grant = strtolower(trim((string)$permission->permission));

		if ($scope === '' || $grant === '') return false;

		if (($scope === 'system' && $grant === 'admin') || ($scope === 'entry' && $grant === 'admin')) {
			return $this->isAdministrator($userId);
		}

		if (str_starts_with($scope, 'ilias:')) {
			$refId = (int)substr($scope, 6);
			if ($refId <= 0) return false;

			return $this->canAccessIliasRefId($userId, $refId, $grant);
		}

		if ($scope === 'ilias' && str_contains($grant, ':')) {
			[$operation, $refId] = explode(':', $grant, 2);
			$operation = trim($operation);
			$refId = (int)$refId;

			if ($operation === '' || $refId <= 0) return false;

			return $this->canAccessIliasRefId($userId, $refId, $operation);
		}

		foreach ($this->getPermissions() as $currentPermission) {
			if (strtolower(trim((string)$currentPermission->scope)) === $scope
				&& strtolower(trim((string)$currentPermission->permission)) === $grant) {
				return true;
			}
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
		if ($user == null || !$this->isAdministrator((int)$user->id)) return array();

		$rows = ilObjUser::_getAllUserData(['login', 'firstname', 'lastname', 'email'], 1);
		$users = array();

		foreach ($rows as $row) {
			$userId = (int)($row['usr_id'] ?? $row['user_id'] ?? 0);
			if ($userId <= 0 || $this->isAnonymousUser($userId)) continue;

			$name = trim(trim((string)($row['firstname'] ?? '')) . ' ' . trim((string)($row['lastname'] ?? '')));
			$login = (string)($row['login'] ?? ilObjUser::_lookupLogin($userId));

			if ($name === '') {
				$name = $login;
			}

			$users[] = User::fromArray(array(
				'id' => (string)$userId,
				'userid' => $login,
				'name' => $name,
				'email' => (string)($row['email'] ?? ilObjUser::_lookupEmail($userId)),
				'lang' => ilObjUser::_lookupLanguage($userId),
				'role' => $this->getCompatibilityRoleName($userId),
				'roles' => $userId === $this->getCurrentUserId() ? $this->getRoles() : array()
			));
		}

		return $users;
	}

	public function getAllGroups() {
		return array();
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
					'info' => (string)($operation['description'] ?? ''),
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
			'accesscontrol' => $this->accesscontrol == null ? 'Fail' : 'Ok',
			'ilAuthSession' => $this->ilAuthSession == null ? 'Fail' : 'Ok',
			'ilUser' => $this->ilObjUser == null ? 'Fail' : 'Ok',
			'rbacreview' => $this->rbacreview == null ? 'Fail' : 'Ok',
			'current_user_id' => $this->getCurrentUserId(),
			'current_user_role' => $this->getCompatibilityRoleName($this->getCurrentUserId())
		);
	}

	// Private methods

	private function getCurrentUserId(): int {
		$userId = 0;

		if ($this->accesscontrol != null) {
			$userId = (int)$this->accesscontrol->getUserId();
		}

		if ($userId <= 0 && $this->ilAuthSession != null) {
			$userId = (int)$this->ilAuthSession->getUserId();
		}

		if ($userId <= 0 && $this->ilObjUser != null) {
			$userId = (int)$this->ilObjUser->getId();
		}

		return $userId;
	}

	private function userExists(int $userId): bool {
		if ($userId <= 0) return false;

		$name = ilObjUser::_lookupName($userId);
		return ((int)($name['user_id'] ?? 0)) > 0;
	}

	private function isAnonymousUser(int $userId): bool {
		if ($userId <= 0) return true;
		if (defined('ANONYMOUS_USER_ID') && $userId === (int)ANONYMOUS_USER_ID) return true;

		return $userId === 13;
	}

	private function isSystemUser(int $userId): bool {
		if ($userId <= 0) return false;
		if (defined('SYSTEM_USER_ID') && $userId === (int)SYSTEM_USER_ID) return true;

		return $userId === 6;
	}

	private function isAdministrator(int $userId): bool {
		if ($userId <= 0 || $this->isAnonymousUser($userId)) return false;
		if ($this->isSystemUser($userId)) return true;
		if ($this->rbacreview == null) return false;

		$globalRoleIds = array_map('intval', $this->rbacreview->assignedGlobalRoles($userId));

		foreach ($globalRoleIds as $roleId) {
			$title = strtolower(trim((string)ilObject::_lookupTitle($roleId)));
			$name = $this->normalizeRoleName($title, '');

			if (in_array($title, ['administrator', 'admin', 'il_role_admin'], true)) return true;
			if (in_array($name, ['administrator', 'admin', 'il_role_admin'], true)) return true;
		}

		return false;
	}

	private function getCompatibilityRoleName(int $userId): string {
		if ($userId <= 0 || $this->isAnonymousUser($userId)) return 'visit';
		if ($this->isAdministrator($userId)) return 'admin';

		return 'member';
	}

	private function createRoleFromIliasRoleId(int $roleId, bool $global): Role {
		$title = (string)ilObject::_lookupTitle($roleId);
		$name = $this->normalizeRoleName($title, 'ilias_role_' . $roleId);

		return Role::fromArray(array(
			'id' => (string)$roleId,
			'name' => $name,
			'label' => $title,
			'info' => $global ? 'ILIAS global role.' : 'ILIAS local or linked role.',
			'archive' => 0,
			'permissions' => array()
		));
	}

	private function normalizeRoleName(string $title, string $fallback): string {
		$name = strtolower(trim($title));
		$name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
		$name = trim($name, '_');

		return $name !== '' ? $name : $fallback;
	}

	private function containsRoleName(array $roles, string $name): bool {
		$name = strtolower(trim($name));

		foreach ($roles as $role) {
			if (strtolower(trim((string)$role->name)) === $name) return true;
		}

		return false;
	}

	private function canAccessIliasRefId(int $userId, int $refId, string $operation): bool {
		if ($this->isAdministrator($userId)) return true;
		if ($this->rbacreview == null) return false;

		$operationId = (int)ilRbacReview::_getOperationIdByName($operation);
		if ($operationId <= 0) return false;

		$assignedRoleIds = array_map('intval', $this->rbacreview->assignedRoles($userId));
		$parentRoles = $this->rbacreview->getParentRoleIds($refId, false);
		$parentRoleIds = array_map('intval', array_keys($parentRoles));
		$relevantRoleIds = array_values(array_intersect($assignedRoleIds, $parentRoleIds));

		foreach ($relevantRoleIds as $roleId) {
			$operationIds = array_map('intval', $this->rbacreview->getRoleOperationsOnObject((int)$roleId, $refId));

			if (in_array($operationId, $operationIds, true)) {
				return true;
			}
		}

		return false;
	}

	private function uniquePermissions(array $permissions): array {
		$out = array();
		$seen = array();

		foreach ($permissions as $permission) {
			$key = strtolower(trim((string)$permission->scope)) . ':' . strtolower(trim((string)$permission->permission));
			if ($key === ':') continue;
			if (isset($seen[$key])) continue;

			$seen[$key] = true;
			$out[] = $permission;
		}

		return $out;
	}
}
