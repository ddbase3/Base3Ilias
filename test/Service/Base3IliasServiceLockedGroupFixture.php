<?php declare(strict_types=1);

namespace Base3Ilias\Test\Service;

use Base3Ilias\Group\AbstractIliasGroup;

final class Base3IliasServiceLockedGroupFixture extends AbstractIliasGroup {

	protected const GROUP_NAME = 'locked';
	protected const DESCRIPTION = 'Code-owned membership';
	protected const EDITABLE = false;
	protected const CRITERIA = ['criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [7]]];
}
