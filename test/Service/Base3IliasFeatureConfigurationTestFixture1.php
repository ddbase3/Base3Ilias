<?php declare(strict_types=1);

namespace Base3Ilias\Test\Service;

use Base3\Api\IClassMap;
use Base3\Authorization\Api\IFeature;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\UserCriterionEvaluator;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;
use PHPUnit\Framework\TestCase;

final class Base3IliasFeatureConfigurationTestFixture1 implements IFeature {
			public static function getName(): string { return 'testauthorizationfeature'; }
			public function getLabel(): string { return 'Test'; }
			public function getDescription(): string { return 'Test feature'; }
			public function getPermissions(): array { return []; }
			public function getDefaultAudience(): array { return ['group' => 'authenticated']; }
		}
