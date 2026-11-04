<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasDatabase;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class Base3IliasDatabaseAvailabilityTest extends TestCase {

	public function testAbsentNativeDatabaseIsNotReportedAsConnected(): void {
		$adapter = $this->disconnectedAdapter();
		self::assertFalse($adapter->connected());
		$adapter->disconnect();
		self::assertFalse($adapter->connected());
		self::assertFalse($adapter->isError());
	}

	public function testConnectRejectsMissingNativeDatabaseAndRecordsError(): void {
		$adapter = $this->disconnectedAdapter();
		try {
			$adapter->connect();
			self::fail('Missing native database was accepted.');
		} catch (RuntimeException $error) {
			self::assertSame($error->getMessage(), $adapter->errorMessage());
		}
		self::assertTrue($adapter->isError());
		self::assertSame(0, $adapter->errorNumber());
	}

	public function testAllTransactionOperationsRequireNativeDatabase(): void {
		foreach (['beginTransaction', 'commit', 'rollback'] as $method) {
			$adapter = $this->disconnectedAdapter();
			try {
				$adapter->$method();
				self::fail('Transaction operation was accepted without a database.');
			} catch (RuntimeException $error) {
				self::assertSame($error->getMessage(), $adapter->errorMessage());
			}
			self::assertTrue($adapter->isError());
		}
	}

	public function testAllReadAndWriteOperationsRequireNativeDatabase(): void {
		foreach (['nonQuery', 'scalarQuery', 'singleQuery', 'listQuery', 'multiQuery'] as $method) {
			$adapter = $this->disconnectedAdapter();
			try {
				$adapter->$method('SELECT 1');
				self::fail('Database operation was accepted without a native connection.');
			} catch (RuntimeException $error) {
				self::assertSame($error->getMessage(), $adapter->errorMessage());
			}
			self::assertSame(0, $adapter->affectedRows());
			self::assertSame(0, $adapter->insertId());
		}
	}

	public function testConnectClearsPreviousErrorBeforeCheckingAgain(): void {
		$adapter = $this->disconnectedAdapter();
		foreach (['connect', 'connect'] as $method) {
			try {
				$adapter->$method();
				self::fail('Missing native database was accepted.');
			} catch (RuntimeException $error) {
				self::assertTrue($adapter->isError());
				self::assertSame($error->getMessage(), $adapter->errorMessage());
			}
		}
	}

	public function testStringEscapeCoversSqlMetacharactersAndControlBytes(): void {
		$adapter = $this->disconnectedAdapter();
		self::assertSame("safe", $adapter->escape('safe'));
		self::assertSame("a\\'b", $adapter->escape("a'b"));
		self::assertSame('a\\\\b', $adapter->escape('a\\b'));
		self::assertSame('a\\nb', $adapter->escape("a\nb"));
		self::assertSame('a\\0b', $adapter->escape("a\0b"));
		self::assertSame('a\\Zb', $adapter->escape("a\x1ab"));
		self::assertFalse($adapter->isError());
	}

	private function disconnectedAdapter(): Base3IliasDatabase {
		return (new ReflectionClass(Base3IliasDatabase::class))->newInstanceWithoutConstructor();
	}
}
