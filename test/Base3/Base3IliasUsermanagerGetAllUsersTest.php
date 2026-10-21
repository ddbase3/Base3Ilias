<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Usermanager\User;
use Base3Ilias\Base3\Base3IliasUsermanager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Unit tests for the existing identity projection used by directory enumeration.
 *
 * No ILIAS classes are replaced, and loading this file has no side effects.
 * Native bulk-query behavior requires a separate ILIAS integration test.
 */
final class Base3IliasUsermanagerGetAllUsersTest extends TestCase {

	public function testDirectoryRowMapsIdentityWithoutResolvingRoles(): void {
		$user = $this->projectDirectoryRow(21, [
			'title' => 'Dr.',
			'firstname' => 'Ada',
			'lastname' => 'Lovelace',
			'login' => 'ada',
			'email' => 'ada@example.test',
			'language' => 'en',
			'active' => 1
		]);

		self::assertInstanceOf(User::class, $user);
		self::assertSame('21', $user->id);
		self::assertSame('ada', $user->userid);
		self::assertSame('Dr. Ada Lovelace', $user->name);
		self::assertSame('ada@example.test', $user->email);
		self::assertSame('en', $user->lang);
		self::assertSame('', $user->role);
		self::assertSame([], $user->roles);
	}

	public function testMissingNameFallsBackToLoginWithoutProfileLookups(): void {
		$user = $this->projectDirectoryRow(22, [
			'login' => 'inactive',
			'email' => 'inactive@example.test',
			'language' => 'de',
			'active' => 0
		]);

		self::assertSame('22', $user->id);
		self::assertSame('inactive', $user->name);
		self::assertSame('inactive@example.test', $user->email);
		self::assertSame('de', $user->lang);
		self::assertSame([], $user->roles);
	}

	private function projectDirectoryRow(int $userId, array $row): User {
		$manager = (new ReflectionClass(Base3IliasUsermanager::class))->newInstanceWithoutConstructor();
		$projection = new ReflectionMethod(Base3IliasUsermanager::class, 'createUser');

		return $projection->invoke($manager, $userId, null, $row);
	}
}
