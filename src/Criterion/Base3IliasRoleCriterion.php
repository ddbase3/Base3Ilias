<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Base3Ilias for BASE3 Framework.
 *
 * Base3Ilias integrates BASE3 with the ILIAS learning platform.
 * It provides native identity, services, assets and lifecycle adapters.
 * Application modules use the shared BASE3 Foundation contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0-or-later
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 ***********************************************************************/


namespace Base3\Base3Ilias\Criterion;

use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\CoreFoundation\Usermanager\Api\IUserCriterion;
use Base3\CoreFoundation\Usermanager\Role;
use Base3\CoreFoundation\Usermanager\User;
use InvalidArgumentException;

/** Matches roles provided by native ILIAS user resolution, without recalculating RBAC. */
final class Base3IliasRoleCriterion implements IUserCriterion {

	public function __construct(private readonly ITranslation $translation) {}

	public static function getName(): string { return 'base3iliasrolecriterion'; }

	public function getSchema(): array {
		return [
			'title' => $this->translation->translate('Criterion', 'base3ilias_role_criterion', 'title', 'ILIAS role'),
			'type' => 'object',
			'required' => ['role_ids'],
			'properties' => [
				'role_ids' => [
					'title' => $this->translation->translate('Criterion', 'base3ilias_role_criterion', 'role_ids', 'Role IDs'),
					'type' => 'array',
					'items' => ['type' => 'integer', 'minimum' => 1],
					'uniqueItems' => true
				]
			],
			'additionalProperties' => false
		];
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
