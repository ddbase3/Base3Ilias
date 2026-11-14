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


namespace Base3\Base3Ilias\Test\Migration;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\Core\Usermanager\UserCriterionEvaluator;
use Base3\Base3Ilias\Migration\Base3IliasFeatureAudienceMigrationProvider;
use Base3\Base3Ilias\Migration\Migration001ConvertFeatureGrants;
use Base3\Base3Ilias\Service\Base3IliasFeatureConfiguration;
use PHPUnit\Framework\TestCase;

final class Base3IliasFeatureAudienceMigrationProviderTest extends TestCase {

	public function testProviderExposesOneVersionedMigrationThatConvertsOldGrants(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['grants' => ['feature' => ['authenticated']]]);
		$store->expects(self::once())->method('set')->with('base3_authorization_features', 'catalog', [
			'rules' => ['feature' => ['any' => [['group' => 'authenticated']]]]
		]);
		$store->expects(self::once())->method('save');
		$map = $this->createMock(IClassMap::class);
		$config = new Base3IliasFeatureConfiguration($store, new UserCriterionEvaluator($map), $map);
		$provider = new Base3IliasFeatureAudienceMigrationProvider($config);
		self::assertSame('base3iliasfeatureaudiencemigrationprovider', $provider::getName());
		self::assertTrue($provider->isActive());
		$migrations = $provider->getMigrations();
		self::assertCount(1, $migrations);
		self::assertInstanceOf(Migration001ConvertFeatureGrants::class, $migrations[0]);
		self::assertSame('migration001convertfeaturegrants', $migrations[0]::getName());
		self::assertSame('20261019002', $migrations[0]->getVersion());
		self::assertNotSame('', $migrations[0]->getDescription());
		$migrations[0]->up();
	}
}
