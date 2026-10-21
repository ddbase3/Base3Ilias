<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasUsermanager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Contract regression tests for native ILIAS user search.
 *
 * Loading this file does not instantiate ILIAS classes or run test code.
 * Live database behavior must be checked in an ILIAS 9, 10 or 11 integration.
 */
final class Base3IliasUsermanagerSearchUsersTest extends TestCase {

	public function testEmptyTermsDoNotAccessTheDatabase(): void {
		$manager = (new ReflectionClass(Base3IliasUsermanager::class))->newInstanceWithoutConstructor();

		self::assertSame([], $manager->searchUsers(''));
		self::assertSame([], $manager->searchUsers("   \t  "));
	}

	public function testInvalidPaginationIsRejectedBeforeDatabaseAccess(): void {
		$manager = (new ReflectionClass(Base3IliasUsermanager::class))->newInstanceWithoutConstructor();

		foreach ([[0, 0], [101, 0], [20, -1]] as [$limit, $offset]) {
			try {
				$manager->searchUsers('ada', $limit, $offset);
				self::fail('Invalid pagination was not rejected.');
			} catch (InvalidArgumentException $expected) {
				self::assertNotSame('', $expected->getMessage());
			}
		}
	}

	public function testSearchIsPreparedAndKeepsSqlWildcardsLiteral(): void {
		[$sql, $types, $values] = $this->buildStatement('a%_!b');

		self::assertSame(count($types), substr_count($sql, '%s'));
		self::assertSame(count($types), count($values));
		self::assertSame('text', $types[0]);
		self::assertSame('language', $values[0]);
		self::assertSame('integer', $types[1]);
		self::assertSame(defined('ANONYMOUS_USER_ID') ? (int)ANONYMOUS_USER_ID : 13, $values[1]);
		self::assertSame(array_fill(0, 5, '%a!%!_!!b%'), array_slice($values, 2));
		self::assertStringNotContainsString('a%_!b', $sql);
		self::assertSame(5, substr_count($sql, "ESCAPE '!'"));
		self::assertStringContainsString('LEFT JOIN usr_pref', $sql);
	}

	public function testSearchMatchesFullNamesAndSortsDeterministically(): void {
		[$sql, $types, $values] = $this->buildStatement('Ada Lovelace');

		self::assertStringContainsString("CONCAT(COALESCE(u.title, ''), ' ', COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, ''))", $sql);
		self::assertStringContainsString('u.login', $sql);
		self::assertStringContainsString('u.email', $sql);
		self::assertStringContainsString('ORDER BY LOWER(', $sql);
		self::assertStringEndsWith('), u.usr_id', $sql);
		self::assertSame(array_fill(0, 5, '%Ada Lovelace%'), array_slice($values, 2));
		self::assertNotContains('integer', array_slice($types, 2));
	}

	public function testNumericSearchAlsoMatchesExactId(): void {
		[$sql, $types, $values] = $this->buildStatement('00042');

		self::assertStringContainsString('u.usr_id = %s', $sql);
		self::assertSame('integer', $types[count($types) - 1]);
		self::assertSame(42, $values[count($values) - 1]);
	}

	public function testNumericOverflowCannotAccidentallyMatchAnotherUser(): void {
		[$sql, $types, $values] = $this->buildStatement('999999999999999999999999999999');

		self::assertStringNotContainsString('u.usr_id = %s', $sql);
		self::assertNotContains('integer', array_slice($types, 2));
		self::assertSame(array_fill(0, 5, '%999999999999999999999999999999%'), array_slice($values, 2));
	}

	private function buildStatement(string $query): array {
		$manager = (new ReflectionClass(Base3IliasUsermanager::class))->newInstanceWithoutConstructor();
		$method = new ReflectionMethod(Base3IliasUsermanager::class, 'buildSearchUsersStatement');
		return $method->invoke($manager, $query);
	}
}
