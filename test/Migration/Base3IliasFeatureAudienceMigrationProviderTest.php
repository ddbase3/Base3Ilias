<?php declare(strict_types=1);

namespace Base3Ilias\Test\Migration;

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\UserCriterionEvaluator;
use Base3Ilias\Migration\Base3IliasFeatureAudienceMigrationProvider;
use Base3Ilias\Migration\Migration001ConvertFeatureGrants;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;
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
