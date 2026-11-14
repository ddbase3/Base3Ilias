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


namespace Base3\Base3Ilias\Test\Service;

use Base3\Base3Ilias\Group\AbstractIliasGroup;

final class Base3IliasServiceLockedGroupFixture extends AbstractIliasGroup {

	protected const GROUP_NAME = 'locked';
	protected const DESCRIPTION = 'Code-owned membership';
	protected const EDITABLE = false;
	protected const CRITERIA = ['criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [7]]];
}
