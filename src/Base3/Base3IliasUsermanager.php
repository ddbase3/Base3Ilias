<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\ICheck;
use Base3\Core\ServiceLocator;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Permission;
use Base3\Usermanager\Role;
use Base3\Usermanager\User;
use ilObject;
use ilObjUser;
use ilRbacReview;

class Base3IliasUsermanager implements IUsermanager, ICheck {

	private const ILIAS_ADMIN_ROLE_NAME = 'global_admin';

	private $servicelocator;
	private $accesscontrol;
	private $ilAuthSession;
	private $ilObjUser;
	private $rbacreview;
	private $rbacsystem;

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
		$this->rbacsystem = $this->servicelocator->get('rbacsystem');

		if ($this->rbacreview == null) {
			$this->rbacreview = $this->servicelocator->get('ilRbacReview');
		}

		if ($this->rbacsystem == null) {
			$this->rbacsystem = $this->servicelocator->get('ilRbacSystem');
		}

		if ($this->rbacreview == null && isset($GLOBALS['DIC']['rbacreview'])) {
			$this->rbacreview = $GLOBALS['DIC']['rbacreview'];
		}

		if ($this->rbacsystem == null && isset($GLOBALS['DIC']['rbacsystem'])) {
			$this->rbacsystem = $GLOBALS['DIC']['rbacsystem'];
		}
	}

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

		// ILIAS group membership is not part of the BASE3 usermanager contract yet.
		// ILIAS object permissions are exposed through roles and object operations.
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

	public function can(Permission $permission): bool {
		$userId = $this->getCurrentUserId();
		if ($userId <= 0 || $this->isAnonymousUser($userId)) return false;

		$scope = strtolower(trim((string)$permission->scope));
		$operation = strtolower(trim((string)$permission->permission));

		if ($scope === '' || $operation === '') return false;

		if (($scope === 'system' && $operation === 'admin')
			|| ($scope === 'entry' && $operation === 'admin')) {
			return $this->isAdministrator($userId);
		}

		if ($scope === 'ilias') {
			$refId = $this->getRefIdFromPermissionTarget($permission->target);
			if ($refId <= 0) return false;

			return $this->canAccessIliasRefId($userId, $refId, $operation);
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
			'accesscontrol' => $this->accesscontrol == null ? 'Fail' : 'Ok',
			'ilUser' => $this->ilObjUser == null ? 'Fail' : 'Ok',
			'rbacreview' => $this->rbacreview == null ? 'Fail' : 'Ok',
			'rbacsystem' => $this->rbacsystem == null ? 'Fail' : 'Ok'
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
		if ($this->accesscontrol != null) {
			$userId = $this->accesscontrol->getUserId();
			if ($userId !== null) {
				return is_numeric($userId) && (int)$userId > 0 ? (int)$userId : 0;
			}
		}

		if ($this->ilObjUser != null) {
			$userId = (int)$this->ilObjUser->getId();
			if ($userId > 0) return $userId;
		}

		if ($this->ilAuthSession != null) {
			$userId = (int)$this->ilAuthSession->getUserId();
			if ($userId > 0) return $userId;
		}

		return 0;
	}

	private function userExists(int $userId): bool {
		if ($userId <= 0) return false;

		$name = ilObjUser::_lookupName($userId);
		return ((int)($name['user_id'] ?? 0)) > 0;
	}

	private function isAnonymousUser(int $userId): bool {
		return $userId === 13;
	}

	private function isAdministrator(int $userId): bool {
		if ($userId <= 0 || $this->isAnonymousUser($userId) || $this->rbacreview == null) {
			return false;
		}

		$globalRoleIds = array_map('intval', $this->rbacreview->assignedGlobalRoles($userId));

		foreach ($globalRoleIds as $roleId) {
			$title = (string)ilObject::_lookupTitle($roleId);

			if ($this->normalizeRoleName($title) === self::ILIAS_ADMIN_ROLE_NAME) {
				return true;
			}
		}

		return false;
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
			'info' => 'Derived from the global ILIAS role global_admin.',
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
				'info' => 'Derived from the global ILIAS role global_admin.',
				'archive' => 0
			)),
			Permission::fromArray(array(
				'scope' => 'entry',
				'permission' => 'admin',
				'label' => 'Entry administration',
				'info' => 'Allows BASE3 entry-admin bypass in embedded ILIAS runtimes.',
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

	private function getRefIdFromPermissionTarget($target): int {
		if (is_int($target) && $target > 0) return $target;

		if (is_string($target) && is_numeric($target) && (int)$target > 0) {
			return (int)$target;
		}

		return 0;
	}

	private function canAccessIliasRefId(int $userId, int $refId, string $operation): bool {
		$operation = strtolower(trim($operation));
		if ($userId <= 0 || $refId <= 0 || $operation === '') return false;

		if ($this->rbacsystem != null && $userId === $this->getCurrentUserId()) {
			return (bool)$this->rbacsystem->checkAccess($operation, $refId);
		}

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
