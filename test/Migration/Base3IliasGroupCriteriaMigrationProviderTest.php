<?php declare(strict_types=1);

namespace Base3Ilias\Test\Migration;

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\UserCriterionEvaluator;
use Base3Ilias\Migration\Base3IliasGroupCriteriaMigrationProvider;
use Base3Ilias\Migration\Migration001ConvertGroupAssignments;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;
use Base3Ilias\Service\Base3IliasGroupConfiguration;
use PHPUnit\Framework\TestCase;

final class Base3IliasGroupCriteriaMigrationProviderTest extends TestCase {

	public function testProviderExposesOneVersionedMigrationThatConvertsOldAssignments(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['assignments' => [
			'testing' => ['user_ids' => [8, 8, -1], 'role_ids' => []]
		]]);
		$store->expects(self::once())->method('set')->with('base3ilias', 'groups', [
			'criteria' => ['testing' => ['any' => [[
				'criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [8]]
			]]]],
			'default_group' => ''
		]);
		$store->expects(self::once())->method('save');
		$map = $this->createMock(IClassMap::class);
		$evaluator = new UserCriterionEvaluator($map);
		$features = new Base3IliasFeatureConfiguration($store, $evaluator, $map);
		$config = new Base3IliasGroupConfiguration($store, $map, $evaluator, $features);
		$provider = new Base3IliasGroupCriteriaMigrationProvider($config);
		self::assertSame('base3iliasgroupcriteriamigrationprovider', $provider::getName());
		self::assertTrue($provider->isActive());
		$migrations = $provider->getMigrations();
		self::assertCount(1, $migrations);
		self::assertInstanceOf(Migration001ConvertGroupAssignments::class, $migrations[0]);
		self::assertSame('migration001convertgroupassignments', $migrations[0]::getName());
		self::assertSame('20261019001', $migrations[0]->getVersion());
		self::assertNotSame('', $migrations[0]->getDescription());
		$migrations[0]->up();
	}
}
