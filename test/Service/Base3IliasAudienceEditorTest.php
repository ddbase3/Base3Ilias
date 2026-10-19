<?php declare(strict_types=1);

namespace Base3Ilias\Test\Service;

use Base3\Api\IClassMap;
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

	private function editor(): Base3IliasAudienceEditor {
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturnCallback(static function(string $interface, string $name) {
			return match ($name) {
				'base3useridcriterion' => new Base3UserIdCriterion(),
				'base3iliasrolecriterion' => new Base3IliasRoleCriterion(),
				'base3emaildomaincriterion' => new Base3EmailDomainCriterion(),
				default => null
			};
		});
		$rbac = $this->createMock(\ilRbacReview::class);
		$rbac->method('getGlobalRoles')->willReturn([]);
		return new Base3IliasAudienceEditor($map, new UserCriterionEvaluator($map), $rbac);
	}
}
