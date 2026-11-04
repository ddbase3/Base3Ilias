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


require_once __DIR__ . '/Base3IliasGroupConfigurationTestFixture1.php';
require_once __DIR__ . '/Base3IliasServiceLockedGroupFixture.php';
require_once __DIR__ . '/Base3IliasServiceEmptyGroupFixture.php';
require_once __DIR__ . '/Base3IliasFeatureConfigurationTestFixture1.php';
final class Base3IliasGroupConfigurationTest extends TestCase {

	public function testLegacyGroupAssignmentsNeedExplicitMigration(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['assignments' => ['development' => ['user_ids' => [21]]]]);
		$store->expects(self::never())->method('set');
		$this->expectException(\RuntimeException::class);
		$this->configuration($store)->get();
	}

	public function testExistingAssignmentsMigrateIntoCriterionExpressions(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['assignments' => [
			'development' => ['role_ids' => [31, 31], 'user_ids' => [21]],
			'testing' => ['role_ids' => [], 'user_ids' => []]
		], 'default_group' => 'presentation']);
		$store->expects(self::once())->method('set')->with('base3ilias', 'groups', self::callback(static function(array $data): bool {
			return !isset($data['assignments']) && $data['criteria']['development']['any'][0]['criterion'] === 'base3useridcriterion'
				&& $data['criteria']['development']['any'][1]['config']['role_ids'] === [31]
				&& $data['criteria']['testing'] === [] && $data['default_group'] === 'presentation';
		}));
		$store->expects(self::once())->method('save');
		$this->configuration($store)->migrateLegacy();
	}

	public function testCustomGroupSharesCanonicalSettingsWithDiscoveredDefinitions(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn([]);
		$store->expects(self::once())->method('set')->with('base3ilias', 'groups', self::callback(static function(array $data): bool {
			return isset($data['groups']['marketing']) && $data['groups']['marketing']['criteria'] === [];
		}));
		$store->expects(self::once())->method('save');
		$config = $this->configuration($store);
		$config->create('marketing', 'Marketing team');
		self::assertContains('marketing', array_map(static fn($g): string => $g->name, $config->getGroups()));
	}

	public function testFrameworkAndIliasGroupDefinitionsHaveUniqueNames(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn([]);
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([
			new Base3AnonymousGroup(),
			new Base3AuthenticatedGroup(),
			new Base3IliasAdministrationGroup(),
			new Base3IliasPresentationGroup(),
			new Base3IliasTestingGroup()
		]);
		$groups = $this->configuration($store, $map)->getGroups();
		self::assertSame(['administration', 'anonymous', 'authenticated', 'presentation', 'testing'],
			array_map(static fn($group): string => $group->name, $groups));
	}

	public function testDuplicateCodeGroupNameIsRejected(): void {
		$store = $this->createMock(ISettingsStore::class);
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([
			new Base3AnonymousGroup(),
			new Base3AnonymousGroup()
		]);
		$this->expectException(\InvalidArgumentException::class);
		$this->configuration($store, $map)->getDefinitions();
	}

	public function testCodeOwnedGroupCannotBeShadowed(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn([]);
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new Base3IliasAdministrationGroup()]);
		$this->expectException(\InvalidArgumentException::class);
		$this->configuration($store, $map)->create('administration', 'Duplicate');
	}

	public function testPrivilegedGroupsAreNeverAllowedAsFallback(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn([]);
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new Base3IliasAdministrationGroup()]);
		$this->expectException(\InvalidArgumentException::class);
		$this->configuration($store, $map)->replaceAll(['development' => []], 'development');
	}

	public function testCodeOwnedMembershipCannotBeRemovedByManagedOverride(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['criteria' => ['development' => [
			'criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [84]]
		]]]);
		$map = $this->createMock(IClassMap::class);
		$group = new Base3IliasGroupConfigurationTestFixture1();
		$map->method('getInstancesByInterface')->willReturn([$group]);
		$map->method('getInstanceByInterfaceName')->willReturnCallback(static fn(string $api, string $name) => match($name) {
			'base3useridcriterion' => new \Base3\Usermanager\Criterion\Base3UserIdCriterion(),
			'base3useridentitycriterion' => new \Base3\Usermanager\Criterion\Base3UserIdentityCriterion(),
			default => null
		});
		$config = $this->configuration($store, $map);
		$entry = $config->getEntries()[0];
		self::assertSame('development', $entry['group']->name);
		self::assertSame(['developer-login'], $entry['codeCriteria']['config']['usernames']);
		self::assertSame([84], $entry['configuredCriteria']['config']['user_ids']);
		$bootstrapUser = new \Base3\Usermanager\User();
		$bootstrapUser->id = '4567';
		$bootstrapUser->userid = 'developer-login';
		$otherUser = new \Base3\Usermanager\User();
		$otherUser->id = '84';
		$otherUser->userid = 'ordinary-user';
		$outsider = new \Base3\Usermanager\User();
		$outsider->id = '95';
		$outsider->userid = 'outsider';
		$evaluator = new UserCriterionEvaluator($map);
		self::assertTrue($evaluator->matches($bootstrapUser, $entry['criteria']));
		self::assertTrue($evaluator->matches($otherUser, $entry['criteria']));
		self::assertFalse($evaluator->matches($outsider, $entry['criteria']));
	}


	public function testSettingsAreReadOnceUntilExplicitMutation(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->expects(self::once())->method('get')->with('base3ilias', 'groups', [])
			->willReturn(['default_group' => 'presentation']);
		$config = $this->configuration($store);
		self::assertSame(['default_group' => 'presentation'], $config->get());
		self::assertSame(['default_group' => 'presentation'], $config->get());
	}

	public function testMigrationDoesNotPersistWhenAssignmentsAreAbsent(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['criteria' => []]);
		$store->expects(self::never())->method('set');
		$store->expects(self::never())->method('save');
		$this->configuration($store)->migrateLegacy();
	}

	public function testMigrationRejectsConflictingSchemasBeforeWriting(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['assignments' => [], 'criteria' => []]);
		$store->expects(self::never())->method('set');
		$store->expects(self::never())->method('save');
		$this->expectException(\InvalidArgumentException::class);
		$this->configuration($store)->migrateLegacy();
	}

	public function testMigrationFiltersDeduplicatesAndSortsPositiveNativeIds(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['assignments' => [
			'testing' => ['user_ids' => [3, '2', 0, -1, 'bad', '2', null], 'role_ids' => [5, '4', 5]],
			'ignored' => 'invalid-entry',
			'empty' => ['user_ids' => 'not-an-array', 'role_ids' => []]
		], 'default_group' => '  presentation  ']);
		$written = null;
		$store->expects(self::once())->method('set')->willReturnCallback(static function(string $group, string $name, array $settings) use (&$written): void {
			self::assertSame('base3ilias', $group);
			self::assertSame('groups', $name);
			$written = $settings;
		});
		$store->expects(self::once())->method('save');
		$this->configuration($store)->migrateLegacy();
		self::assertSame(['any' => [
			['criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [2, 3]]],
			['criterion' => 'base3iliasrolecriterion', 'config' => ['role_ids' => [4, 5]]]
		]], $written['criteria']['testing']);
		self::assertSame([], $written['criteria']['empty']);
		self::assertArrayNotHasKey('ignored', $written['criteria']);
		self::assertArrayNotHasKey('assignments', $written);
		self::assertSame('presentation', $written['default_group']);
	}

	public function testDiscoverableGroupsAreSortedCachedAndIgnoreNonGroups(): void {
		$store = $this->createMock(ISettingsStore::class);
		$map = $this->createMock(IClassMap::class);
		$map->expects(self::once())->method('getInstancesByInterface')->willReturn([
			new Base3IliasPresentationGroup(), new \stdClass(), new Base3IliasServiceLockedGroupFixture(), new Base3IliasAdministrationGroup()
		]);
		$config = $this->configuration($store, $map);
		$names = static fn(array $defs): array => array_map(static fn(IGroup $g): string => $g->getGroup()->name, $defs);
		self::assertSame(['administration', 'locked', 'presentation'], $names($config->getDefinitions()));
		self::assertSame(['administration', 'locked', 'presentation'], $names($config->getDefinitions()));
	}

	public function testEmptyDiscoveredGroupNameIsRejected(): void {
		$store = $this->createMock(ISettingsStore::class);
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new Base3IliasServiceEmptyGroupFixture()]);
		$this->expectException(\InvalidArgumentException::class);
		$this->configuration($store, $map)->getDefinitions();
	}

	public function testLockedCodeGroupsIgnoreHostConfiguredRules(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['criteria' => ['locked' => ['criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [99]]]]]);
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new Base3IliasServiceLockedGroupFixture()]);
		$entry = $this->configuration($store, $map)->getEntries()[0];
		self::assertFalse($entry['editable']);
		self::assertSame('code', $entry['source']);
		self::assertSame(['criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [7]]], $entry['criteria']);
		self::assertSame([], $entry['configuredCriteria']);
	}

	public function testEditableCodeGroupsUseHostRulesWhenCodeRulesAreEmpty(): void {
		$override = ['criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [42]]];
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['criteria' => ['presentation' => $override]]);
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new Base3IliasPresentationGroup()]);
		$entry = $this->configuration($store, $map)->getEntries()[0];
		self::assertTrue($entry['editable']);
		self::assertSame([], $entry['codeCriteria']);
		self::assertSame($override, $entry['configuredCriteria']);
		self::assertSame($override, $entry['criteria']);
	}

	public function testManagedGroupDtosHaveStableSortedNamesAndDescriptions(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['groups' => [
			'zeta' => ['description' => 'Z group', 'criteria' => []],
			'alpha' => ['description' => 'A group', 'criteria' => ['criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [42]]]]
		]]);
		$entries = $this->configuration($store)->getEntries();
		self::assertSame(['alpha', 'zeta'], array_map(static fn(array $row): string => $row['group']->name, $entries));
		self::assertSame('A group', $entries[0]['group']->info);
		self::assertSame('managed', $entries[0]['source']);
		self::assertTrue($entries[0]['editable']);
		self::assertSame($entries[0]['configuredCriteria'], $entries[0]['criteria']);
		self::assertSame([], $entries[0]['codeCriteria']);
	}

	public function testMalformedManagedGroupDefinitionsAreRejected(): void {
		foreach ([
			'invalid-list',
			['Zed' => ['description' => 'Z', 'criteria' => []]],
			['x' => ['description' => 'X', 'criteria' => []]],
			['valid' => ['description' => 3, 'criteria' => []]],
			['valid' => ['description' => 'Valid', 'criteria' => 'bad']],
			['valid' => false]
		] as $groups) {
			$store = $this->createMock(ISettingsStore::class);
			$store->method('get')->willReturn(['groups' => $groups]);
			try {
				$this->configuration($store)->getEntries();
				self::fail('Invalid managed group definitions must be rejected.');
			} catch (\InvalidArgumentException $error) {
				self::assertNotSame('', $error->getMessage());
			}
		}
	}

	public function testManagedGroupCannotShadowDiscoveredGroup(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['groups' => ['administration' => ['description' => 'Shadow', 'criteria' => []]]]);
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new Base3IliasAdministrationGroup()]);
		$this->expectException(\InvalidArgumentException::class);
		$this->configuration($store, $map)->getEntries();
	}

	public function testMalformedConfiguredCodeCriteriaCannotReplaceCodeRules(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['criteria' => ['administration' => 'invalid']]);
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new Base3IliasAdministrationGroup()]);
		$this->expectException(\InvalidArgumentException::class);
		$this->configuration($store, $map)->getEntries();
	}

	public function testLockedCodeGroupCannotBeOverriddenOrDeleted(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn([]);
		$store->expects(self::never())->method('set');
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new Base3IliasServiceLockedGroupFixture()]);
		$config = $this->configuration($store, $map);
		try {
			$config->replaceAll(['locked' => []]);
			self::fail('Locked code group should not accept host overrides.');
		} catch (\InvalidArgumentException $error) {
			self::assertNotSame('', $error->getMessage());
		}
		try {
			$config->remove('locked');
			self::fail('Locked code group should not be removable.');
		} catch (\InvalidArgumentException $error) {
			self::assertNotSame('', $error->getMessage());
		}
	}

	public function testInvalidGroupExpressionsCannotBePersisted(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn([]);
		$store->expects(self::never())->method('set');
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new Base3IliasPresentationGroup()]);
		$this->expectException(\InvalidArgumentException::class);
		$this->configuration($store, $map)->replaceAll(['presentation' => ['group' => 'development']]);
	}

	public function testReplaceAllClearsOmittedEditableOverridesButKeepsDefinitions(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['criteria' => ['presentation' => ['criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [42]]]]]);
		$store->expects(self::once())->method('set')->with('base3ilias', 'groups', self::callback(static function(array $settings): bool {
			return ($settings['criteria']['presentation'] ?? null) === [] && ($settings['default_group'] ?? null) === '';
		}));
		$store->expects(self::once())->method('save');
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new Base3IliasPresentationGroup()]);
		$config = $this->configuration($store, $map);
		$config->replaceAll([]);
		self::assertSame([], $config->getEntries()[0]['configuredCriteria']);
		self::assertSame('presentation', $config->getGroups()[0]->name);
	}

	public function testReplaceAllCanSetEditableManagedDefaultGroup(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['groups' => ['marketing' => ['description' => 'Marketing', 'criteria' => []]]]);
		$store->expects(self::once())->method('set')->with('base3ilias', 'groups', self::callback(static function(array $data): bool {
			return $data['default_group'] === 'marketing' && $data['groups']['marketing']['criteria'] === [];
		}));
		$store->expects(self::once())->method('save');
		$this->configuration($store)->replaceAll(['marketing' => []], 'marketing');
	}

	public function testCreatingManagedGroupTrimsNameAndDescription(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn([]);
		$store->expects(self::once())->method('set')->with('base3ilias', 'groups', self::callback(static function(array $data): bool {
			return $data['groups']['marketing'] === ['description' => 'Marketing team', 'criteria' => []];
		}));
		$store->expects(self::once())->method('save');
		$this->configuration($store)->create('  marketing  ', '  Marketing team  ');
	}

	public function testInvalidManagedGroupNamesAndDescriptionsCannotBeSaved(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn([]);
		$store->expects(self::never())->method('set');
		$config = $this->configuration($store);
		foreach ([
			['x', 'Valid'], ['UPPER', 'Valid'], ['bad/name', 'Valid'],
			['name', '  '], ['name', str_repeat('x', 251)], [str_repeat('a', 65), 'Valid']
		] as [$name, $description]) {
			try {
				$config->create($name, $description);
				self::fail('Invalid managed group metadata must be rejected.');
			} catch (\InvalidArgumentException $error) {
				self::assertNotSame('', $error->getMessage());
			}
		}
	}

	public function testDuplicateManagedGroupNameCannotBeCreated(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['groups' => ['marketing' => ['description' => 'Existing', 'criteria' => []]]]);
		$store->expects(self::never())->method('set');
		$this->expectException(\InvalidArgumentException::class);
		$this->configuration($store)->create('marketing', 'Duplicate');
	}

	public function testCannotRemoveDefaultManagedGroup(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['default_group' => 'marketing', 'groups' => ['marketing' => ['description' => 'Existing', 'criteria' => []]]]);
		$store->expects(self::never())->method('set');
		$this->expectException(\InvalidArgumentException::class);
		$this->configuration($store)->remove('marketing');
	}

	public function testCannotRemoveManagedGroupReferencedByFeatureDefault(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturnCallback(static function(string $group): array {
			return $group === 'base3ilias'
				? ['groups' => ['authenticated' => ['description' => 'Existing', 'criteria' => []]]]
				: [];
		});
		$store->expects(self::never())->method('set');
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturnCallback(static function(string $interface): array {
			return $interface === \Base3\Authorization\Api\IFeature::class ? [new Base3IliasFeatureConfigurationTestFixture1()] : [];
		});
		$this->expectException(\InvalidArgumentException::class);
		$this->configuration($store, $map)->remove('authenticated');
	}

	public function testRemovingUnreferencedManagedGroupPreservesOthersAndCachesResult(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['groups' => [
			'marketing' => ['description' => 'Marketing', 'criteria' => []],
			'general' => ['description' => 'General', 'criteria' => []]
		]]);
		$store->expects(self::once())->method('set')->with('base3ilias', 'groups', self::callback(static function(array $data): bool {
			return !isset($data['groups']['marketing']) && isset($data['groups']['general']);
		}));
		$store->expects(self::once())->method('save');
		$config = $this->configuration($store);
		$config->remove('marketing');
		self::assertSame(['general'], array_map(static fn($group): string => $group->name, $config->getGroups()));
	}

	private function configuration(ISettingsStore $store, ?IClassMap $map = null): Base3IliasGroupConfiguration {
		if ($map === null) {
			$map = $this->createStub(IClassMap::class);
			$map->method('getInstancesByInterface')->willReturn([]);
		}
		$evaluator = new UserCriterionEvaluator($map);
		return new Base3IliasGroupConfiguration($store, $map, $evaluator, new Base3IliasFeatureConfiguration($store, $evaluator, $map));
	}
}
