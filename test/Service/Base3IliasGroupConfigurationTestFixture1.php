<?php declare(strict_types=1);

namespace Base3Ilias\Test\Service;

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\Api\IGroup;
use Base3\Usermanager\Definition\Base3AnonymousGroup;
use Base3\Usermanager\Definition\Base3AuthenticatedGroup;
use Base3\Usermanager\UserCriterionEvaluator;
use Base3Ilias\Group\Base3IliasAdministrationGroup;
use Base3Ilias\Group\Base3IliasPresentationGroup;
use Base3Ilias\Group\Base3IliasTestingGroup;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;
use Base3Ilias\Service\Base3IliasGroupConfiguration;
use PHPUnit\Framework\TestCase;

final class Base3IliasGroupConfigurationTestFixture1 implements IGroup {
			public static function getName(): string { return 'bootstrapdevelopmenttestgroup'; }
			public function getGroup(): \Base3\Usermanager\Group {
				return \Base3\Usermanager\Group::fromArray(['id' => 'development', 'name' => 'development', 'info' => 'Test', 'archive' => 0, 'roles' => []]);
			}
			public function getCriteria(): array { return ['criterion' => 'base3useridentitycriterion', 'config' => ['usernames' => ['developer-login']]]; }
			public function isEditable(): bool { return true; }
		}
