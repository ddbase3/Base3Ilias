<?php declare(strict_types=1);

namespace Base3Ilias\Test\Service;

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Group;
use Base3\Usermanager\UserCriterionEvaluator;
use Base3\Usermanager\Api\IGroup;
use Base3\Authorization\Api\IFeature;
use Base3\Usermanager\Api\IUserCriterion;
use Base3\Usermanager\Criterion\Base3UserIdCriterion;
use Base3\Usermanager\Criterion\Base3EmailDomainCriterion;
use Base3Ilias\Group\Base3IliasAdministrationGroup;
use Base3Ilias\Group\Base3IliasPresentationGroup;
use Base3Ilias\Service\Base3IliasAudienceAdministrationAdapter;
use Base3Ilias\Service\Base3IliasAudienceEditor;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;
use Base3Ilias\Service\Base3IliasGroupConfiguration;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

require_once __DIR__ . '/Base3IliasFeatureConfigurationTestFixture1.php';
require_once __DIR__ . '/Base3IliasServiceFeatureFixture.php';

final class Base3IliasAudienceAdministrationAdapterTest extends TestCase {

	public function testNativeAdministrationMembershipAloneDoesNotGrantBase3Administration(): void {
		$usermanager = $this->createMock(IUsermanager::class);
		$usermanager->method('getGroups')->willReturn([$this->group('administration')]);
		$adapter = $this->adapter($usermanager);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('BASE3 development membership required.');
		$adapter->createGroup('marketing', 'Marketing');
	}

	public function testDevelopmentMembershipPassesTheAdaptersOwnAdditionalGuard(): void {
		$usermanager = $this->createMock(IUsermanager::class);
		$usermanager->expects(self::once())->method('getGroups')->willReturn([$this->group('development')]);
		$this->adapter($usermanager)->assertCanAdminister();
	}


	public function testEveryAdministrativeEntryPointRejectsMissingDevelopmentMembership(): void {
		$usermanager = $this->createMock(IUsermanager::class);
		$usermanager->method('getGroups')->willReturn([$this->group('administration')]);
		$adapter = $this->adapter($usermanager);
		foreach ([
			static fn() => $adapter->getGroupView(),
			static fn() => $adapter->replaceGroupRules([], ''),
			static fn() => $adapter->createGroup('marketing', 'Marketing'),
			static fn() => $adapter->removeGroup('marketing'),
			static fn() => $adapter->getFeatureView(),
			static fn() => $adapter->replaceFeatureRules([])
		] as $action) {
			try {
				$action();
				self::fail('Missing development membership must deny all administration entry points.');
			} catch (RuntimeException $error) {
				self::assertSame('BASE3 development membership required.', $error->getMessage());
			}
		}
	}

	public function testGroupViewCombinesDiscoveredAndManagedEntriesWithoutChangingCodeCriteria(): void {
		$settings = ['base3ilias' => ['groups' => [
			'groups' => ['marketing' => ['description' => 'Marketing team', 'criteria' => ['criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [42]]]]],
			'default_group' => 'marketing'
		]]];
		$adapter = $this->serviceAdapter($settings);
		$view = $adapter->getGroupView();
		self::assertSame(['administration', 'marketing', 'presentation'], array_column($view['entries'], 'name'));
		self::assertSame('marketing', $view['defaultGroup']);
		self::assertSame('managed', $view['entries'][1]['source']);
		self::assertSame([['kind' => 'user', 'value' => '42', 'config' => '{}']], $view['entries'][1]['rows']);
		self::assertSame([], $view['entries'][0]['codeCriteria']);
		self::assertSame([], $view['roleOptions']);
		self::assertSame(['base3emaildomaincriterion', 'base3useridcriterion'], array_column($view['criterionOptions'], 'name'));
		self::assertStringContainsString('local roles', $view['roleHint']);
	}

	public function testReplacingGroupRulesUpdatesBothEditableCodeAndManagedSettings(): void {
		$settings = ['base3ilias' => ['groups' => [
			'groups' => ['marketing' => ['description' => 'Marketing team', 'criteria' => []]]
		]]];
		$adapter = $this->serviceAdapter($settings);
		$adapter->replaceGroupRules([
			'presentation' => [['kind' => 'user', 'value' => '42']],
			'marketing' => [['kind' => 'user', 'value' => '17']]
		], 'marketing');
		$dataset = $settings['base3ilias']['groups'];
		self::assertSame('marketing', $dataset['default_group']);
		self::assertSame([42], $dataset['criteria']['presentation']['any'][0]['config']['user_ids']);
		self::assertSame([17], $dataset['groups']['marketing']['criteria']['any'][0]['config']['user_ids']);
		self::assertSame([], $dataset['criteria']['administration']);
	}

	public function testOmittedFeatureRulesBecomeExplicitEmptyAudiences(): void {
		$settings = [];
		$adapter = $this->serviceAdapter($settings);
		$adapter->replaceFeatureRules([
			'base3iliasservicefeaturefixture' => [['kind' => 'group', 'value' => 'presentation']]
		]);
		$rules = $settings['base3_authorization_features']['catalog']['rules'];
		self::assertSame(['any' => [['group' => 'presentation']]], $rules['base3iliasservicefeaturefixture']);
		self::assertSame([], $rules['testauthorizationfeature']);
	}

	public function testFeatureViewReportsDiscoveredPermissionsAndCurrentOverrides(): void {
		$settings = ['base3_authorization_features' => ['catalog' => ['rules' => [
			'base3iliasservicefeaturefixture' => ['group' => 'administration']
		]]]];
		$view = $this->serviceAdapter($settings)->getFeatureView();
		self::assertSame(['base3iliasservicefeaturefixture', 'testauthorizationfeature'], array_column($view['rows'], 'name'));
		self::assertSame(['reports/read', 'reports/edit'], $view['rows'][0]['permissions']);
		self::assertSame('Service feature', $view['rows'][0]['label']);
		self::assertSame('Read and edit reports', $view['rows'][0]['description']);
		self::assertSame([['kind' => 'group', 'value' => 'administration', 'config' => '{}']], $view['rows'][0]['rules']);
		self::assertSame(['administration', 'presentation'], $view['groups']);
		self::assertSame([], $view['roleOptions']);
	}

	public function testUnknownOrMalformedGroupRowsAreRejectedBeforePersistence(): void {
		foreach ([
			['missing' => []],
			['presentation' => 'invalid'],
			['presentation' => [['kind' => 'group', 'value' => 'administration']]],
			['presentation' => [['kind' => 'role', 'value' => 'invalid']]]
		] as $rows) {
			$settings = [];
			$adapter = $this->serviceAdapter($settings);
			try {
				$adapter->replaceGroupRules($rows, '');
				self::fail('Invalid group rows must fail before persistence.');
			} catch (\InvalidArgumentException $error) {
				self::assertSame([], $settings);
			}
		}
	}

	public function testUnknownOrMalformedFeatureRowsAreRejectedBeforePersistence(): void {
		foreach ([
			['missingfeature' => []],
			['base3iliasservicefeaturefixture' => 'invalid'],
			['base3iliasservicefeaturefixture' => [['kind' => 'group', 'value' => 'missing']]],
			['base3iliasservicefeaturefixture' => [['kind' => 'user', 'value' => 0]]]
		] as $rows) {
			$settings = [];
			$adapter = $this->serviceAdapter($settings);
			try {
				$adapter->replaceFeatureRules($rows);
				self::fail('Invalid feature rows must fail before persistence.');
			} catch (\InvalidArgumentException $error) {
				self::assertSame([], $settings);
			}
		}
	}

	public function testAdapterCreatesAndRemovesManagedGroupThroughCanonicalSettings(): void {
		$settings = [];
		$adapter = $this->serviceAdapter($settings);
		$adapter->createGroup('marketing', 'Marketing');
		self::assertSame('Marketing', $settings['base3ilias']['groups']['groups']['marketing']['description']);
		$adapter->removeGroup('marketing');
		self::assertArrayNotHasKey('marketing', $settings['base3ilias']['groups']['groups']);
	}

	public function testFeatureCatalogRejectsDuplicateNamesBeforeRendering(): void {
		$settings = [];
		$adapter = $this->serviceAdapter($settings, [
			new Base3IliasServiceFeatureFixture(), new Base3IliasServiceFeatureFixture()
		]);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Duplicate BASE3 feature.');
		$adapter->getFeatureView();
	}

	public function testFeatureCatalogIgnoresUnrelatedDiscoveredObjects(): void {
		$settings = [];
		$adapter = $this->serviceAdapter($settings, [new \stdClass(), new Base3IliasServiceFeatureFixture()]);
		self::assertSame(['base3iliasservicefeaturefixture'], array_column($adapter->getFeatureView()['rows'], 'name'));
	}

	private function serviceAdapter(array &$settings, ?array $featureDefinitions = null): Base3IliasAudienceAdministrationAdapter {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturnCallback(static function(string $group, string $name, array $default = []) use (&$settings): array {
			return $settings[$group][$name] ?? $default;
		});
		$store->method('set')->willReturnCallback(static function(string $group, string $name, array $value) use (&$settings): void {
			$settings[$group][$name] = $value;
		});
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturnCallback(static function(string $interface) use ($featureDefinitions): array {
			return match ($interface) {
				IGroup::class => [new Base3IliasAdministrationGroup(), new Base3IliasPresentationGroup()],
				IFeature::class => $featureDefinitions ?? [new Base3IliasFeatureConfigurationTestFixture1(), new Base3IliasServiceFeatureFixture()],
				IUserCriterion::class => [new Base3UserIdCriterion(), new Base3EmailDomainCriterion()],
				default => []
			};
		});
		$map->method('getInstanceByInterfaceName')->willReturnCallback(static function(string $interface, string $name) {
			return match ($name) {
				'base3useridcriterion' => new Base3UserIdCriterion(),
				'base3emaildomaincriterion' => new Base3EmailDomainCriterion(),
				default => null
			};
		});
		$evaluator = new UserCriterionEvaluator($map);
		$features = new Base3IliasFeatureConfiguration($store, $evaluator, $map);
		$groups = new Base3IliasGroupConfiguration($store, $map, $evaluator, $features);
		$rbac = $this->createMock(\ilRbacReview::class);
		$rbac->method('getGlobalRoles')->willReturn([]);
		$editor = new Base3IliasAudienceEditor($map, $evaluator, $rbac);
		$usermanager = $this->createMock(IUsermanager::class);
		$usermanager->method('getGroups')->willReturn([$this->group('development')]);
		return new Base3IliasAudienceAdministrationAdapter($groups, $features, $editor, $map, $usermanager);
	}

	private function adapter(IUsermanager $usermanager): Base3IliasAudienceAdministrationAdapter {
		$store = $this->createMock(ISettingsStore::class);
		$map = $this->createMock(IClassMap::class);
		$evaluator = new UserCriterionEvaluator($map);
		$features = new Base3IliasFeatureConfiguration($store, $evaluator, $map);
		$groups = new Base3IliasGroupConfiguration($store, $map, $evaluator, $features);
		// The editor is not invoked by assertCanAdminister or the denied mutation.
		$unusedEditor = (new ReflectionClass(Base3IliasAudienceEditor::class))->newInstanceWithoutConstructor();
		return new Base3IliasAudienceAdministrationAdapter($groups, $features, $unusedEditor, $map, $usermanager);
	}

	private function group(string $name): Group {
		return Group::fromArray(['id' => $name, 'name' => $name, 'info' => '', 'archive' => 0, 'roles' => []]);
	}
}
