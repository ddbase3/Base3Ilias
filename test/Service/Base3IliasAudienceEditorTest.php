<?php declare(strict_types=1);

namespace Base3Ilias\Test\Service;

use Base3\Api\IClassMap;
use Base3\Translation\Api\ITranslation;
use Base3\Usermanager\Criterion\Base3EmailDomainCriterion;
use Base3\Usermanager\Criterion\Base3UserIdCriterion;
use Base3\Usermanager\UserCriterionEvaluator;
use Base3Ilias\Criterion\Base3IliasRoleCriterion;
use Base3Ilias\Service\Base3IliasAudienceEditor;
use PHPUnit\Framework\TestCase;

final class Base3IliasAudienceEditorTest extends TestCase {

	public function testGroupFormSupportsUsersNativeRolesAndAnyDiscoverableCriterion(): void {
		$editor = $this->editor();
		$expression = $editor->parse([
			['kind' => 'user', 'value' => '42'],
			['kind' => 'role', 'value' => '23'],
			['kind' => 'criterion', 'value' => 'base3emaildomaincriterion', 'config' => '{"domain":"qualitus.de"}']
		], null, true);
		self::assertSame('base3useridcriterion', $expression['any'][0]['criterion']);
		self::assertSame('base3iliasrolecriterion', $expression['any'][1]['criterion']);
		self::assertSame('qualitus.de', $expression['any'][2]['config']['domain']);
		self::assertCount(3, $editor->describe($expression));
	}

	public function testFeatureFormSupportsGroupsUsersRolesAndCriteria(): void {
		$expression = $this->editor()->parse([
			['kind' => 'group', 'value' => 'development'],
			['kind' => 'user', 'value' => '42'],
			['kind' => 'role', 'value' => '23'],
			['kind' => 'criterion', 'value' => 'base3emaildomaincriterion', 'config' => '{"domain":"qualitus.de"}']
		], ['development'], true);
		self::assertSame('development', $expression['any'][0]['group']);
		self::assertSame('base3iliasrolecriterion', $expression['any'][2]['criterion']);
		self::assertCount(4, $expression['any']);
	}

	public function testUnknownFeatureGroupFailsBeforeSave(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->editor()->parse([['kind' => 'group', 'value' => 'missing']], ['authenticated'], false);
	}

	public function testInvalidJsonCriterionFailsBeforeSave(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->editor()->parse([['kind' => 'criterion', 'value' => 'base3emaildomaincriterion', 'config' => '{']], null, true);
	}

	public function testInvalidCriterionSchemaFailsBeforeSave(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->editor()->parse([['kind' => 'criterion', 'value' => 'base3emaildomaincriterion', 'config' => '{"email":"foo"}']], null, true);
	}


	public function testCriteriaCatalogIsSortedAndSkipsForeignObjects(): void {
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([
			new \Base3\Usermanager\Criterion\Base3UserIdCriterion(),
			new \stdClass(),
			new Base3EmailDomainCriterion()
		]);
		$rbac = $this->createMock(\ilRbacReview::class);
		$editor = new Base3IliasAudienceEditor($map, new UserCriterionEvaluator($map), $rbac);
		$options = $editor->getCriteria();
		self::assertSame(['base3emaildomaincriterion', 'base3useridcriterion'], array_column($options, 'name'));
		self::assertIsArray($options[0]['schema']);
		self::assertIsArray($options[1]['schema']);
	}

	public function testDuplicateDiscoveredCriterionIsRejected(): void {
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([
			new \Base3\Usermanager\Criterion\Base3UserIdCriterion(),
			new \Base3\Usermanager\Criterion\Base3UserIdCriterion()
		]);
		$rbac = $this->createMock(\ilRbacReview::class);
		$editor = new Base3IliasAudienceEditor($map, new UserCriterionEvaluator($map), $rbac);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Duplicate BASE3 criterion.');
		$editor->getCriteria();
	}

	public function testRoleOptionsDiscardNonPositiveNativeRoleIds(): void {
		$map = $this->createMock(IClassMap::class);
		$rbac = $this->createMock(\ilRbacReview::class);
		$rbac->method('getGlobalRoles')->willReturn([0, -5, 0]);
		$editor = new Base3IliasAudienceEditor($map, new UserCriterionEvaluator($map), $rbac);
		self::assertSame([], $editor->getRoleOptions());
	}

	public function testEmptyFormProducesNoAudience(): void {
		self::assertSame([], $this->editor()->parse([], null, false));
		self::assertSame([], $this->editor()->describe([]));
	}

	public function testExpressionRowRoundTripsComplexGroupLogic(): void {
		$editor = $this->editor();
		$nested = ['all' => [
			['group' => 'authenticated'],
			['not' => ['group' => 'blocked']]
		]];
		$expression = $editor->parse([
			['kind' => 'expression', 'config' => json_encode($nested, JSON_THROW_ON_ERROR)]
		], ['authenticated', 'blocked'], false);
		self::assertSame(['any' => [$nested]], $expression);
		self::assertSame($expression, $editor->parse($editor->describe($expression), ['authenticated', 'blocked'], false));
	}

	public function testSingleUserAndRoleExpressionsUseDedicatedRowsOnDescription(): void {
		$editor = $this->editor();
		$expression = $editor->parse([
			['kind' => 'user', 'value' => 42],
			['kind' => 'role', 'value' => '23']
		], null, true);
		self::assertSame([
			['kind' => 'user', 'value' => '42', 'config' => '{}'],
			['kind' => 'role', 'value' => '23', 'config' => '{}']
		], $editor->describe($expression));
	}

	public function testMultipleUserIdsRemainAnAdvancedCriterionOnDescription(): void {
		$editor = $this->editor();
		$rows = $editor->describe(['criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [3, 5]]]);
		self::assertSame('criterion', $rows[0]['kind']);
		self::assertSame('base3useridcriterion', $rows[0]['value']);
		self::assertSame(['user_ids' => [3, 5]], json_decode($rows[0]['config'], true, 512, JSON_THROW_ON_ERROR));
	}

	public function testEmptyCriterionConfigurationIsDescribedAsJsonObject(): void {
		$rows = $this->editor()->describe(['criterion' => 'customcriterion', 'config' => []]);
		self::assertSame([['kind' => 'criterion', 'value' => 'customcriterion', 'config' => '{}']], $rows);
	}

	public function testMalformedPersistedExpressionCannotBeDescribed(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->editor()->describe(['any' => ['invalid-leaf']]);
	}

	public function testRoleAssignmentsAreRejectedWhenNotAllowed(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Native roles are not allowed');
		$this->editor()->parse([['kind' => 'role', 'value' => '42']], null, false);
	}

	public function testGroupAssignmentsAreRejectedWhenGroupReferencesAreForbidden(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->editor()->parse([['kind' => 'group', 'value' => 'development']], null, true);
	}

	public function testUnknownCriterionCannotBypassValidation(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->editor()->parse([['kind' => 'criterion', 'value' => 'missingcriterion', 'config' => '{}']], null, true);
	}

	public function testMoreThanOneHundredRowsAreRejectedBeforeParsing(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Too many BASE3 audience rules.');
		$this->editor()->parse(array_fill(0, 101, ['kind' => 'user', 'value' => '1']), null, false);
	}

	public function testMalformedRowAndUnknownKindAreRejected(): void {
		$this->assertInvalidRow('broken');
		$this->assertInvalidRow(['kind' => 1]);
		$this->assertInvalidRow(['kind' => 'other', 'value' => '42']);
		$this->assertInvalidRow(['kind' => 'criterion', 'value' => '']);
	}

	public function testNativeIdsMustBePositiveCanonicalIntegers(): void {
		foreach ([0, -3, '0', '-3', '2.5', 'invalid', 1.2, true, [], null] as $value) {
			$this->assertInvalidRow(['kind' => 'user', 'value' => $value]);
		}
		$this->assertInvalidRow(['kind' => 'role', 'value' => '-1']);
	}

	public function testAudienceJsonMustBeABoundedObject(): void {
		foreach ([[], 12, '', '{', 'null', 'false', '123', '[1]', str_repeat('a', 20001)] as $value) {
			$this->assertInvalidRow(['kind' => 'expression', 'config' => $value]);
		}
	}

	private function assertInvalidRow(mixed $row): void {
		try {
			$this->editor()->parse([$row], ['development'], true);
			self::fail('Expected the invalid audience row to be rejected.');
		} catch (\InvalidArgumentException $error) {
			self::assertNotSame('', $error->getMessage());
		}
	}

	private function editor(): Base3IliasAudienceEditor {
		$map = $this->createMock(IClassMap::class);
		$translation = $this->createStub(ITranslation::class);
		$map->method('getInstanceByInterfaceName')->willReturnCallback(static function(string $interface, string $name) use ($translation) {
			return match ($name) {
				'base3useridcriterion' => new Base3UserIdCriterion(),
				'base3iliasrolecriterion' => new Base3IliasRoleCriterion($translation),
				'base3emaildomaincriterion' => new Base3EmailDomainCriterion(),
				default => null
			};
		});
		$rbac = $this->createMock(\ilRbacReview::class);
		$rbac->method('getGlobalRoles')->willReturn([]);
		return new Base3IliasAudienceEditor($map, new UserCriterionEvaluator($map), $rbac);
	}
}
