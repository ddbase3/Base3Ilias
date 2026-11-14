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
use Base3\CoreFoundation\Authorization\Api\IFeature;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\Core\Usermanager\UserCriterionEvaluator;
use Base3\Base3Ilias\Service\Base3IliasFeatureConfiguration;
use PHPUnit\Framework\TestCase;

final class Base3IliasFeatureConfigurationTestFixture1 implements IFeature {
			public static function getName(): string { return 'testauthorizationfeature'; }
			public function getLabel(): string { return 'Test'; }
			public function getDescription(): string { return 'Test feature'; }
			public function getPermissions(): array { return []; }
			public function getDefaultAudience(): array { return ['group' => 'authenticated']; }
		}
