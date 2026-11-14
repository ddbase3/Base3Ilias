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

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\CoreFoundation\Usermanager\Api\IGroup;
use Base3\Core\Usermanager\Definition\Base3AnonymousGroup;
use Base3\Core\Usermanager\Definition\Base3AuthenticatedGroup;
use Base3\Core\Usermanager\UserCriterionEvaluator;
use Base3\Base3Ilias\Group\Base3IliasAdministrationGroup;
use Base3\Base3Ilias\Group\Base3IliasPresentationGroup;
use Base3\Base3Ilias\Group\Base3IliasTestingGroup;
use Base3\Base3Ilias\Service\Base3IliasFeatureConfiguration;
use Base3\Base3Ilias\Service\Base3IliasGroupConfiguration;
use PHPUnit\Framework\TestCase;

final class Base3IliasGroupConfigurationTestFixture1 implements IGroup {
			public static function getName(): string { return 'bootstrapdevelopmenttestgroup'; }
			public function getGroup(): \Base3\CoreFoundation\Usermanager\Group {
				return \Base3\CoreFoundation\Usermanager\Group::fromArray(['id' => 'development', 'name' => 'development', 'info' => 'Test', 'archive' => 0, 'roles' => []]);
			}
			public function getCriteria(): array { return ['criterion' => 'base3useridentitycriterion', 'config' => ['usernames' => ['developer-login']]]; }
			public function isEditable(): bool { return true; }
		}
