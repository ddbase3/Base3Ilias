<?php declare(strict_types=1);

namespace Base3Ilias\Test\Service;

use Base3\Api\IClassMap;
use Base3\Authorization\Api\IFeature;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\UserCriterionEvaluator;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;
use PHPUnit\Framework\TestCase;

final class Base3IliasFeatureConfigurationTest extends TestCase {

	public function testEmptyHostOverrideNeverRestoresCodeDefaults(): void {
		$feature = $this->feature();
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['rules' => ['testauthorizationfeature' => []]]);
		self::assertSame([], $this->configuration($store)->getAudience($feature));
	}

	public function testSingleDatasetStoresCriteriaAndGroups(): void {
		$store = $this->createMock(ISettingsStore::class);
		$rules = ['testauthorizationfeature' => ['any' => [
			['group' => 'authenticated'],
			['criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [42]]]
		]]];
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturnCallback(static function($api, $name) {
			return $name === 'base3useridcriterion' ? new \Base3\Usermanager\Criterion\Base3UserIdCriterion() : null;
		});
		$store->expects(self::once())->method('set')->with('base3_authorization_features', 'catalog', ['rules' => $rules]);
		$store->expects(self::once())->method('save');
		$this->configuration($store, $map)->replaceAll($rules, ['authenticated']);
	}

	public function testLegacyGrantsAreConvertedOnlyByMigration(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['grants' => ['testauthorizationfeature' => ['authenticated', 'development'], 'blocked' => []]]);
		$store->expects(self::once())->method('set')->with('base3_authorization_features', 'catalog', [
			'rules' => [
				'testauthorizationfeature' => ['any' => [['group' => 'authenticated'], ['group' => 'development']]],
				'blocked' => []
			]
		]);
		$store->expects(self::once())->method('save');
		$this->configuration($store)->migrateLegacy();
	}

	public function testNoImplicitRuntimeLegacyRead(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['grants' => ['testauthorizationfeature' => ['authenticated']]]);
		$this->expectException(\RuntimeException::class);
		$this->configuration($store)->getAudience($this->feature());
	}

	public function testUnknownFeatureGroupCannotBeSaved(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->expects(self::never())->method('set');
		$this->expectException(\InvalidArgumentException::class);
		$this->configuration($store)->replaceAll(['feature' => ['group' => 'missing']], ['authenticated']);
	}

	private function configuration(ISettingsStore $store, ?IClassMap $map = null): Base3IliasFeatureConfiguration {
		$map ??= $this->createMock(IClassMap::class);
		return new Base3IliasFeatureConfiguration($store, new UserCriterionEvaluator($map), $map);
	}

	private function feature(): IFeature {
		return new class implements IFeature {
			public static function getName(): string { return 'testauthorizationfeature'; }
			public function getLabel(): string { return 'Test'; }
			public function getDescription(): string { return 'Test feature'; }
			public function getPermissions(): array { return []; }
			public function getDefaultAudience(): array { return ['group' => 'authenticated']; }
		};
	}
}
