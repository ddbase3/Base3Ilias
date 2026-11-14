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


namespace Base3\Base3Ilias\Group;

use Base3\CoreFoundation\Usermanager\Api\IGroup;
use Base3\CoreFoundation\Usermanager\Group;

/** Shared presentation of immutable code group definitions, not a second group service. */
abstract class AbstractIliasGroup implements IGroup {

	protected const GROUP_NAME = '';
	protected const DESCRIPTION = '';
	protected const EDITABLE = false;
	protected const CRITERIA = [];

	public static function getName(): string {
		$parts = explode('\\', static::class);
		return strtolower(end($parts));
	}

	public function getGroup(): Group {
		return Group::fromArray([
			'id' => static::GROUP_NAME,
			'name' => static::GROUP_NAME,
			'info' => static::DESCRIPTION,
			'archive' => 0,
			'roles' => []
		]);
	}

	public function getCriteria(): array { return static::CRITERIA; }

	public function isEditable(): bool { return static::EDITABLE; }
}
