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
use ilDBInterface;
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
		private readonly Base3IliasGroupConfiguration $groupConfiguration,
		private readonly ilDBInterface $database
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

	/**
	 * Returns native ILIAS user-folder identities without resolving per-user roles.
	 *
	 * The native query includes active and inactive accounts in ILIAS 9 to 11.
	 * This method provides identity data only; callers exposing the directory
	 * must independently enforce the required host authorization.
	 *
	 * @return User[] Native non-anonymous users with ID, login, name, email and language
	 */
	public function getAllUsers(): array {
		$users = array();

		foreach (ilObjUser::_getUsersForFolder(USER_FOLDER_ID, -1) as $data) {
			$userId = (int)($data['usr_id'] ?? 0);
			if ($userId <= 0 || $this->isAnonymousUser($userId)) continue;

			$users[] = $this->createUser($userId, null, $data);
		}

		return $users;
	}


	/**
	 * Searches native ILIAS identities with one bounded, parameterized database query.
	 *
	 * Includes inactive accounts, but never the anonymous user. Query text is
	 * matched literally against login, names and email; digits also match an
	 * exact native user ID. No native profile or RBAC lookups run per result.
	 *
	 * @param string $query Plain text, not SQL; surrounding whitespace is ignored
	 * @param int $limit Maximum page size (1..100)
	 * @param int $offset Non-negative zero-based result offset
	 * @return User[] ILIAS identities without resolved role memberships
	 * @throws \InvalidArgumentException On invalid pagination
	 * @throws \Throwable If the native ILIAS database query fails
	 */
	public function searchUsers(string $query, int $limit = 20, int $offset = 0): array {
		if ($limit < 1 || $limit > 100 || $offset < 0) {
			throw new \InvalidArgumentException('User search requires a limit of 1..100 and a non-negative offset.');
		}

		$query = trim($query);
		if ($query === '') return [];

		[$sql, $types, $values] = $this->buildSearchUsersStatement($query);
		$this->database->setLimit($limit, $offset);
		$result = $this->database->queryF($sql, $types, $values);
		$users = [];

		try {
			while ($row = $this->database->fetchAssoc($result)) {
				$userId = (int)($row['usr_id'] ?? 0);
				if ($userId <= 0 || $this->isAnonymousUser($userId)) continue;
				$users[] = $this->createUser($userId, null, $row);
			}
		} finally {
			$this->database->free($result);
		}

		return $users;
	}

	/**
	 * Creates a prepared ILIAS user-directory search with a deterministic order.
	 *
	 * ILIAS ilUserQuery covers basic text search, but not the contract's exact
	 * numeric ID and concatenated display-name matching. This query reads the
	 * same usr_data/ usr_pref fields as getAllUsers(), with native pagination.
	 *
	 * @return array{string, array<int,string>, array<int,string|int>}
	 */
	private function buildSearchUsersStatement(string $query): array {
		// '!' is the explicit SQL LIKE escape character across supported hosts.
		// Escape it first; '%' and '_' from user input are then literal characters.
		$pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
		$fullName = "CONCAT(COALESCE(u.title, ''), ' ', COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, ''))";
		$fields = ['u.login', 'u.firstname', 'u.lastname', $fullName, 'u.email'];
		$conditions = [];
		$types = ['text', 'integer'];
		$values = ['language', defined('ANONYMOUS_USER_ID') ? (int)ANONYMOUS_USER_ID : 13];

		foreach ($fields as $field) {
			$conditions[] = "LOWER($field) LIKE LOWER(%s) ESCAPE '!'";
			$types[] = 'text';
			$values[] = $pattern;
		}

		// Reject overflow instead of silently casting large numeric text.
		$numericId = ctype_digit($query)
			? filter_var(ltrim($query, '0'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
			: false;
		if ($numericId !== false) {
			$conditions[] = 'u.usr_id = %s';
			$types[] = 'integer';
			$values[] = $numericId;
		}

		$sql = 'SELECT u.usr_id, u.login, u.title, u.firstname, u.lastname, u.email, p.value AS language'
			. ' FROM usr_data u'
			. ' LEFT JOIN usr_pref p ON p.usr_id = u.usr_id AND p.keyword = %s'
			. ' WHERE u.usr_id != %s AND (' . implode(' OR ', $conditions) . ')'
			. ' ORDER BY LOWER(' . $fullName . '), u.usr_id';

		return [$sql, $types, $values];
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

	/**
	 * Projects native identity fields into a BASE3 user.
	 *
	 * The individual lookup path still fetches native roles and profile data.
	 * Bulk rows already contain the identity fields and do not trigger lookups.
	 *
	 * @param int $userId Native ILIAS user ID
	 * @param array|null $roles Native roles, or null when not resolved for directory entries
	 * @param array|null $data Native directory row, or null for an individual lookup
	 * @return User Projected BASE3 identity without additional persistence
	 */
	private function createUser(int $userId, ?array $roles, ?array $data = null): User {
		if ($data === null) {
			$name = ilObjUser::_lookupName($userId);
			$data = array(
				'title' => $name['title'] ?? '',
				'firstname' => $name['firstname'] ?? '',
				'lastname' => $name['lastname'] ?? '',
				'login' => ilObjUser::_lookupLogin($userId),
				'email' => ilObjUser::_lookupEmail($userId),
				'language' => ilObjUser::_lookupLanguage($userId)
			);
		}

		$login = (string)($data['login'] ?? '');
		$fullname = trim(trim((string)($data['title'] ?? '')) . ' ' . trim((string)($data['firstname'] ?? '')) . ' ' . trim((string)($data['lastname'] ?? '')));
		if ($fullname === '') $fullname = $login;

		$user = new User();
		$user->id = (string)$userId;
		$user->userid = $login;
		$user->name = $fullname;
		$user->email = (string)($data['email'] ?? '');
		$user->lang = (string)($data['language'] ?? '');
		$user->role = $roles !== null ? $this->getCompatibilityRoleName($userId) : '';
		$user->roles = $roles ?? array();

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
