<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Usermanager\Permission;
use Base3\Usermanager\Role;
use Base3Ilias\Base3\Base3IliasUsermanager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class Base3IliasUsermanagerSafeBoundariesTest extends TestCase {

	public function testInvalidAndAnonymousUserIdsNeverTriggerNativeProfileLookups(): void {
		$manager = $this->manager();
		$anonymous = defined('ANONYMOUS_USER_ID') ? (int) ANONYMOUS_USER_ID : 13;
		foreach ([0, -1, 'abc', '', (string)$anonymous] as $id) {
			self::assertSame(null, $manager->getUserById($id));
		}
	}

	public function testNormalizationAcceptsPositiveNumericIdsOnly(): void {
		self::assertSame(42, $this->invoke('normalizeUserId', 42));
		self::assertSame(42, $this->invoke('normalizeUserId', '42'));
		self::assertSame(null, $this->invoke('normalizeUserId', 0));
		self::assertSame(null, $this->invoke('normalizeUserId', '-4'));
		self::assertSame(null, $this->invoke('normalizeUserId', 'unknown'));
	}

	public function testAnonymousAndMissingIdentitiesHaveNoPrivilegedCompatibilityRole(): void {
		$anonymous = defined('ANONYMOUS_USER_ID') ? (int) ANONYMOUS_USER_ID : 13;
		self::assertTrue($this->invoke('isAnonymousUser', $anonymous));
		self::assertFalse($this->invoke('isAnonymousUser', $anonymous + 1));
		self::assertSame('visit', $this->invoke('getCompatibilityRoleName', 0));
		self::assertSame('visit', $this->invoke('getCompatibilityRoleName', $anonymous));
	}

	public function testNativeUsermanagerDoesNotImplementIndependentRightsMutations(): void {
		$manager = $this->manager();
		$role = (new ReflectionClass(Role::class))->newInstanceWithoutConstructor();
		$permission = (new ReflectionClass(Permission::class))->newInstanceWithoutConstructor();
		self::assertFalse($manager->registUser('user', 'password'));
		self::assertFalse($manager->changePassword('old', 'new'));
		self::assertFalse($manager->assignRoleToUser(1, $role));
		self::assertFalse($manager->revokeRoleFromUser(1, $role));
		self::assertFalse($manager->assignRoleToGroup(1, $role));
		self::assertFalse($manager->revokeRoleFromGroup(1, $role));
		self::assertFalse($manager->addPermissionToRole($role, $permission));
		self::assertFalse($manager->removePermissionFromRole($role, $permission));
	}

	public function testDependencyCheckReportsDeclaredHostDependencies(): void {
		self::assertSame([
			'accesscontrol' => 'Ok', 'ilUser' => 'Ok', 'rbacreview' => 'Ok'
		], $this->manager()->checkDependencies());
	}

	private function manager(): Base3IliasUsermanager {
		return (new ReflectionClass(Base3IliasUsermanager::class))->newInstanceWithoutConstructor();
	}

	private function invoke(string $name, mixed ...$arguments): mixed {
		return (new ReflectionMethod(Base3IliasUsermanager::class, $name))->invoke($this->manager(), ...$arguments);
	}
}
