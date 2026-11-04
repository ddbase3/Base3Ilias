<?php declare(strict_types=1);

namespace Base3Ilias\Test\Service;

use Base3\Api\IClassMap;
use Base3\Authorization\Api\IFeature;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\UserCriterionEvaluator;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;
use PHPUnit\Framework\TestCase;


require_once __DIR__ . '/Base3IliasFeatureConfigurationTestFixture1.php';
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


	public function testAbsentOverrideUsesFeatureDefaultAudience(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn([]);
		self::assertSame(['group' => 'authenticated'], $this->configuration($store)->getAudience($this->feature()));
	}

	public function testExplicitOverrideIsLoadedOnceAndCached(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->expects(self::once())->method('get')->with('base3_authorization_features', 'catalog', [])
			->willReturn(['rules' => ['testauthorizationfeature' => ['group' => 'presentation']]]);
		$config = $this->configuration($store);
		self::assertSame(['group' => 'presentation'], $config->getAudience($this->feature()));
		self::assertSame(['group' => 'presentation'], $config->getAudience($this->feature()));
	}

	public function testSavingEmptyAudienceOverridesCodeDefaultImmediately(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->expects(self::once())->method('set')->with('base3_authorization_features', 'catalog', ['rules' => ['testauthorizationfeature' => []]]);
		$store->expects(self::once())->method('save');
		$config = $this->configuration($store);
		$config->replaceAll(['testauthorizationfeature' => []], ['authenticated']);
		self::assertSame([], $config->getAudience($this->feature()));
	}

	public function testRejectedFeatureDefinitionsCannotBePersisted(): void {
		foreach ([
			[7 => []],
			['' => []],
			['feature' => 'invalid'],
			['feature' => ['group' => 'missing']],
			['feature' => ['criterion' => 'unknowncriterion', 'config' => []]]
		] as $rules) {
			$store = $this->createMock(ISettingsStore::class);
			$store->expects(self::never())->method('set');
			$store->expects(self::never())->method('save');
			try {
				$this->configuration($store)->replaceAll($rules, ['authenticated']);
				self::fail('Invalid feature rules must be rejected before persistence.');
			} catch (\InvalidArgumentException $error) {
				self::assertNotSame('', $error->getMessage());
			}
		}
	}

	public function testMigrationDoesNothingWithoutLegacyGrants(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['rules' => ['testauthorizationfeature' => []]]);
		$store->expects(self::never())->method('set');
		$store->expects(self::never())->method('save');
		$this->configuration($store)->migrateLegacy();
	}

	public function testMigrationRejectsConflictingAndMalformedGrantsBeforeWriting(): void {
		foreach ([
			['grants' => [], 'rules' => []],
			['grants' => 'invalid'],
			['grants' => [4 => []]],
			['grants' => ['feature' => 'invalid']],
			['grants' => ['feature' => ['group' => true]]],
			['grants' => ['feature' => [' ']]],
			['grants' => ['feature' => ['named' => 'authenticated']]]
		] as $dataset) {
			$store = $this->createMock(ISettingsStore::class);
			$store->method('get')->willReturn($dataset);
			$store->expects(self::never())->method('set');
			$store->expects(self::never())->method('save');
			try {
				$this->configuration($store)->migrateLegacy();
				self::fail('Invalid legacy audience settings must not be migrated.');
			} catch (\InvalidArgumentException $error) {
				self::assertNotSame('', $error->getMessage());
			}
		}
	}

	public function testLegacyMigrationInvalidatesPreviouslyLoadedRules(): void {
		$dataset = ['rules' => ['testauthorizationfeature' => ['group' => 'authenticated']]];
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturnCallback(static function() use (&$dataset): array { return $dataset; });
		$store->method('set')->willReturnCallback(static function(string $group, string $name, array $value) use (&$dataset): void { $dataset = $value; });
		$store->expects(self::once())->method('save');
		$config = $this->configuration($store);
		self::assertSame(['group' => 'authenticated'], $config->getAudience($this->feature()));
		$dataset = ['grants' => ['testauthorizationfeature' => ['presentation']]];
		$config->migrateLegacy();
		self::assertSame(['any' => [['group' => 'presentation']]], $config->getAudience($this->feature()));
	}

	public function testMalformedStoredAudienceCatalogIsRejected(): void {
		foreach ([
			['rules' => 'invalid'],
			['rules' => [['group' => 'authenticated']]],
			['rules' => [4 => []]],
			['rules' => ['testauthorizationfeature' => 'invalid']]
		] as $dataset) {
			$store = $this->createMock(ISettingsStore::class);
			$store->method('get')->willReturn($dataset);
			try {
				$this->configuration($store)->getAudience($this->feature());
				self::fail('Malformed feature settings must not be used.');
			} catch (\InvalidArgumentException $error) {
				self::assertNotSame('', $error->getMessage());
			}
		}
	}

	public function testGroupReferenceFallsBackToCodeDefault(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn([]);
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new \stdClass(), $this->feature()]);
		$config = $this->configuration($store, $map);
		self::assertTrue($config->referencesGroup('authenticated'));
		self::assertFalse($config->referencesGroup('presentation'));
	}

	public function testGroupReferenceFindsNestedAnyAllAndNotLeaves(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['rules' => ['testauthorizationfeature' => [
			'all' => [
				['group' => 'presentation'],
				['not' => ['any' => [['group' => 'blocked'], ['group' => 'suspended']]]]
			]
		]]]);
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([$this->feature()]);
		$config = $this->configuration($store, $map);
		foreach (['presentation', 'blocked', 'suspended'] as $name) self::assertTrue($config->referencesGroup($name));
		self::assertFalse($config->referencesGroup('authenticated'));
	}

	public function testEmptyOverrideRemovesReferencesFromCodeDefault(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['rules' => ['testauthorizationfeature' => []]]);
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([$this->feature()]);
		self::assertFalse($this->configuration($store, $map)->referencesGroup('authenticated'));
	}

	private function configuration(ISettingsStore $store, ?IClassMap $map = null): Base3IliasFeatureConfiguration {
		$map ??= $this->createMock(IClassMap::class);
		return new Base3IliasFeatureConfiguration($store, new UserCriterionEvaluator($map), $map);
	}

	private function feature(): IFeature {
		return new Base3IliasFeatureConfigurationTestFixture1();
	}
}
