<?php declare(strict_types=1);

namespace Base3Ilias\Criterion;

use Base3\Usermanager\Api\IUserCriterion;
use Base3\Usermanager\Role;
use Base3\Usermanager\User;
use InvalidArgumentException;

/** Matches roles provided by native ILIAS user resolution, without recalculating RBAC. */
final class Base3IliasRoleCriterion implements IUserCriterion {

	public static function getName(): string { return 'base3iliasrolecriterion'; }

	public function getSchema(): array {
		return ['type' => 'object', 'required' => ['role_ids'], 'properties' => [
			'role_ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'uniqueItems' => true]
		], 'additionalProperties' => false];
	}

	public function matches(?User $user, array $config): bool {
		$roleIds = $config['role_ids'] ?? null;
		if (!is_array($roleIds) || !array_is_list($roleIds)) throw new InvalidArgumentException('role_ids must be a list.');
		if ($user === null || $roleIds === []) return false;
		$ids = array_map('intval', $roleIds);
		foreach ($user->roles as $role) {
			if ($role instanceof Role && is_numeric($role->id) && in_array((int)$role->id, $ids, true)) return true;
		}
		return false;
	}
}
