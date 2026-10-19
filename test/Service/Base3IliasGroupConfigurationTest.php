<?php declare(strict_types=1);

namespace Base3Ilias\Test\Service;

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\Api\IGroup;
use Base3\Usermanager\UserCriterionEvaluator;
use Base3Ilias\Group\Base3IliasAdministrationGroup;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;
use Base3Ilias\Service\Base3IliasGroupConfiguration;
use PHPUnit\Framework\TestCase;

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
		$group = new class implements IGroup {
			public static function getName(): string { return 'bootstrapdevelopmenttestgroup'; }
			public function getGroup(): \Base3\Usermanager\Group {
				return \Base3\Usermanager\Group::fromArray(['id' => 'development', 'name' => 'development', 'info' => 'Test', 'archive' => 0, 'roles' => []]);
			}
			public function getCriteria(): array { return ['criterion' => 'base3useridentitycriterion', 'config' => ['usernames' => ['developer-login']]]; }
			public function isEditable(): bool { return true; }
		};
		$map->method('getInstancesByInterface')->willReturn([$group]);
		$map->method('getInstanceByInterfaceName')->willReturnCallback(static fn(string $api, string $name) => $name === 'base3useridcriterion' ? new \Base3\Usermanager\Criterion\Base3UserIdCriterion() : null);
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

	private function configuration(ISettingsStore $store, ?IClassMap $map = null): Base3IliasGroupConfiguration {
		$map ??= $this->createMock(IClassMap::class);
		$evaluator = new UserCriterionEvaluator($map);
		return new Base3IliasGroupConfiguration($store, $map, $evaluator, new Base3IliasFeatureConfiguration($store, $evaluator, $map));
	}
}
