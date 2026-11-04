<?php declare(strict_types=1);

namespace Base3Ilias\Test\Display;

use Base3Ilias\Display\IliasPermissionDebugDisplay;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class IliasPermissionDebugDisplayRoleSelectionTest extends TestCase {

	public function testRelevantRoleIdsAreLimitedToAssignedRolesPresentInTheNativeParentSet(): void {
		$roles = $this->invoke('getRelevantRoleIds', ['15', 8, 15, 77], [8 => [], 15 => [], 34 => []]);
		self::assertSame([8, 15, 15], $roles);
		self::assertSame([], $this->invoke('getRelevantRoleIds', [99], [8 => []]));
	}

	public function testPermissionOperationsAreSortedAndRetainUnknownNumericIdentifiers(): void {
		self::assertSame("3 read\n9\n17 write", $this->invoke('formatOperations', [17, 9, 3], [
			3 => ['name' => 'read'],
			17 => ['name' => 'write']
		]));
	}

	public function testEmptyRoleOperationSetFormatsAsEmptyText(): void {
		self::assertSame('', $this->invoke('formatOperations', [], []));
	}

	public function testValuesForDebugRowsAreNormalizedWithoutSerializationErrors(): void {
		self::assertSame('yes', $this->invoke('formatValue', true));
		self::assertSame('no', $this->invoke('formatValue', false));
		self::assertSame('', $this->invoke('formatValue', null));
		self::assertSame('stdClass', $this->invoke('formatValue', new \stdClass()));
	}


	public function testMissingTargetHasOnlyPromptRowsAndNoParentRoles(): void {
		$rows = $this->invoke('getTargetRows', 0);
		self::assertSame(['base3_target_ref_id', 'target'], array_column($rows, 'key'));
		self::assertSame([], $this->invoke('getParentRoles', 0));
		self::assertSame([], $this->invoke('getRoleOperationMap', 0, [1, 2]));
	}

	public function testParentRolesAreSortedAndIncludeAssignmentAndProtectionFlags(): void {
		$roles = $this->invoke('getParentRoleRows', [
			'15' => ['title' => 'Role Fifteen', 'role_type' => 'local', 'parent' => 1, 'protected' => true],
			'3' => ['title' => 'Role Three', 'role_type' => 'global', 'parent' => 0, 'protected' => false],
		], ['3']);
		self::assertSame([3, 15], array_column($roles, 'role_id'));
		self::assertSame([true, false], array_column($roles, 'assigned'));
		self::assertSame([false, true], array_column($roles, 'protected'));
		self::assertSame(['Role Three', 'Role Fifteen'], array_column($roles, 'title'));
	}

	public function testRolePermissionRowsMapOperationsWithoutAccessingUnrelatedNativeRoles(): void {
		$parent = [
			'8' => ['title' => 'Local', 'role_type' => 'local', 'parent' => 7],
			'4' => ['title' => 'Global', 'role_type' => 'global', 'parent' => 0],
		];
		$rows = $this->invoke('getRolePermissionRows', $parent, [8, 4], [
			8 => [7, 2], 4 => [2],
		], [2 => ['name' => 'read'], 7 => ['name' => 'write']]);
		self::assertSame([4, 8], array_column($rows, 'role_id'));
		self::assertSame(['2 read', "2 read\n7 write"], array_column($rows, 'operations'));
		self::assertSame(['Global', 'Local'], array_column($rows, 'title'));
	}

	public function testUnknownOperationsAndRepeatedRolesDoNotReceiveInferredPermissions(): void {
		self::assertSame('1\n4', str_replace("\n", '\\n', $this->invoke('formatOperations', [4, 1], [])));
		self::assertSame([], $this->invoke('getRelevantRoleIds', [], [1 => []]));
		self::assertSame([1], $this->invoke('getRelevantRoleIds', [1, 2], [1 => [], 3 => []]));
		self::assertSame(['label' => 'Authorized', 'key' => 'active', 'value' => 'no'], $this->invoke('row', 'Authorized', 'active', false));
	}

	public function testTargetSelectionValidatesPositiveNumericRequestValues(): void {
		$request = $this->createMock(\Base3\Api\IRequest::class);
		$selected = '15';
		$request->method('request')->willReturnCallback(static function(string $key, mixed $default = null) use (&$selected): mixed {
			self::assertSame('base3_target_ref_id', $key);
			return $selected;
		});
		$display = (new ReflectionClass(IliasPermissionDebugDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasPermissionDebugDisplay::class, 'request'))->setValue($display, $request);
		$method = new ReflectionMethod(IliasPermissionDebugDisplay::class, 'getRequestTargetRefId');
		self::assertSame(15, $method->invoke($display));
		foreach (['', '-1', 'unknown', [], 0] as $selected) {
			self::assertSame(0, $method->invoke($display));
		}
	}


	public function testRequestTargetAndUserPromptsNeverTriggerNativeObjectLookup(): void {
		$rows = $this->invoke('getTargetRows', -1);
		self::assertSame(['base3_target_ref_id', 'target'], array_column($rows, 'key'));
		self::assertStringContainsString('No target ref_id', $rows[1]['value']);
	}

	public function testNativeRoleOperationsAreMappedWithoutInventingPermissions(): void {
		if (!class_exists(\ilRbacReview::class)) {
			self::markTestSkipped('Native ILIAS RBAC service is unavailable outside ILIAS.');
		}
		$rbac = $this->createMock(\ilRbacReview::class);
		$rbac->method('getOperations')->willReturn([
			['ops_id' => 3, 'operation' => 'read', 'description' => 'Read'],
			['ops_id' => 0, 'operation' => 'invalid'],
			['ops_id' => -2, 'operation' => 'invalid'],
			['ops_id' => 9, 'operation' => 'write', 'description' => 'Write'],
		]);
		$rbac->method('getRoleOperationsOnObject')->willReturnCallback(
			static fn(int $roleId, int $refId): array => $roleId === 8 && $refId === 5 ? ['3', '9'] : []
		);
		$rbac->method('getParentRoleIds')->willReturn([8 => ['title' => 'Parent']]);
		$display = (new ReflectionClass(IliasPermissionDebugDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasPermissionDebugDisplay::class, 'rbacreview'))->setValue($display, $rbac);
		$map = (new ReflectionMethod(IliasPermissionDebugDisplay::class, 'getOperationMap'))->invoke($display);
		self::assertSame([3, 9], array_keys($map));
		self::assertSame('read', $map[3]['name']);
		$roles = (new ReflectionMethod(IliasPermissionDebugDisplay::class, 'getParentRoles'))->invoke($display, 5);
		self::assertSame([8 => ['title' => 'Parent']], $roles);
		$operations = (new ReflectionMethod(IliasPermissionDebugDisplay::class, 'getRoleOperationMap'))->invoke($display, 5, [8, 11]);
		self::assertSame([8 => [3, 9], 11 => []], $operations);
	}

	private function invoke(string $method, mixed ...$args): mixed {
		$instance = (new ReflectionClass(IliasPermissionDebugDisplay::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(IliasPermissionDebugDisplay::class, $method))->invoke($instance, ...$args);
	}
}
