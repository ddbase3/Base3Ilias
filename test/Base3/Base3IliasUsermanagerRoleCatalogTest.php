<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Usermanager\Permission;
use Base3\Usermanager\Role;
use Base3Ilias\Base3\Base3IliasUsermanager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class Base3IliasUsermanagerRoleCatalogTest extends TestCase {

	public function testRoleNamesNormalizeWhitespacePunctuationAndBlankTitles(): void {
		self::assertSame('course_administrator', $this->invoke('normalizeRoleName', ' Course Administrator '));
		self::assertSame('unit_a_b', $this->invoke('normalizeRoleName', '__UNIT--A..B__'));
		self::assertSame('role', $this->invoke('normalizeRoleName', '!?___'));
		self::assertSame('role', $this->invoke('normalizeRoleName', ' '));
	}

	public function testRoleNameLookupIsCaseInsensitiveAndRequiresARealMatch(): void {
		$roles = [
			Role::fromArray(['id' => '41', 'name' => 'course_editor', 'label' => 'Course Editor', 'permissions' => []]),
			Role::fromArray(['id' => '42', 'name' => 'tutor', 'label' => 'Tutor', 'permissions' => []])
		];
		self::assertTrue($this->invoke('containsRoleName', $roles, ' COURSE_EDITOR '));
		self::assertTrue($this->invoke('containsRoleName', $roles, 'tutor'));
		self::assertFalse($this->invoke('containsRoleName', $roles, 'admin'));
		self::assertFalse($this->invoke('containsRoleName', [], 'tutor'));
	}

	public function testPermissionDeduplicationNormalizesTheKeyButKeepsTheFirstObject(): void {
		$first = Permission::fromArray(['scope' => 'ILIAS', 'permission' => 'Read', 'label' => 'Read']);
		$duplicate = Permission::fromArray(['scope' => ' ilias ', 'permission' => ' READ ', 'label' => 'Duplicate']);
		$other = Permission::fromArray(['scope' => 'system', 'permission' => 'read', 'label' => 'Other scope']);
		$missingScope = Permission::fromArray(['scope' => '', 'permission' => 'read']);
		$missingName = Permission::fromArray(['scope' => 'ilias', 'permission' => '']);

		$unique = $this->invoke('uniquePermissions', [$first, $duplicate, $other, $missingScope, $missingName]);
		self::assertCount(2, $unique);
		self::assertSame($first, $unique[0]);
		self::assertSame($other, $unique[1]);
	}

	public function testAdministratorPermissionsAreExplicitSystemScopeOnly(): void {
		$permissions = $this->invoke('getAdministratorPermissions');
		self::assertCount(1, $permissions);
		self::assertInstanceOf(Permission::class, $permissions[0]);
		self::assertSame('system', $permissions[0]->scope);
		self::assertSame('admin', $permissions[0]->permission);
	}

	public function testAdministratorRoleCarriesOnlyDerivedSystemPermission(): void {
		$role = $this->invoke('createAdministratorRole');
		self::assertInstanceOf(Role::class, $role);
		self::assertSame('base3:admin', (string)$role->id);
		self::assertSame('admin', (string)$role->name);
		self::assertCount(1, $role->permissions);
		self::assertSame('admin', $role->permissions[0]->permission);
	}

	private function invoke(string $method, mixed ...$arguments): mixed {
		$manager = (new ReflectionClass(Base3IliasUsermanager::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(Base3IliasUsermanager::class, $method))->invoke($manager, ...$arguments);
	}
}
