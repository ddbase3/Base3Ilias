<?php declare(strict_types=1);

namespace Base3Ilias\Group;

use Base3\Usermanager\Api\IGroup;
use Base3\Usermanager\Group;

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
