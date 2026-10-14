<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasDatabase;
use ilDBPdoInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Base3IliasDatabaseTest extends TestCase {

	private bool $hadDic = false;
	private mixed $originalDic = null;

	protected function setUp(): void {
		parent::setUp();
		$this->hadDic = array_key_exists('DIC', $GLOBALS);
		$this->originalDic = $GLOBALS['DIC'] ?? null;
	}

	protected function tearDown(): void {
		if($this->hadDic) {
			$GLOBALS['DIC'] = $this->originalDic;
		} else {
			unset($GLOBALS['DIC']);
		}

		parent::tearDown();
	}

	public function testInsertUsesNativeIdWithoutExtraSql(): void {
		$sql = "INSERT INTO example_table (name) VALUES ('test')";
		$db = $this->createMock(ilDBPdoInterface::class);
		$db->expects(self::once())->method('manipulate')->with($sql)->willReturn(1);
		$db->expects(self::once())->method('getLastInsertId')->willReturn(123);
		$db->expects(self::never())->method('query');

		$adapter = $this->createAdapter($db);
		$adapter->nonQuery($sql);

		self::assertSame(1, $adapter->affectedRows());
		self::assertSame(123, $adapter->insertId());
		self::assertFalse($adapter->isError());
	}

	public function testSubsequentUpdateAndDeleteDoNotExposeStaleInsertId(): void {
		$db = $this->createMock(ilDBPdoInterface::class);
		$db->expects(self::exactly(3))->method('manipulate')->willReturnOnConsecutiveCalls(1, 2, 1);
		$db->expects(self::exactly(3))->method('getLastInsertId')->willReturnOnConsecutiveCalls(123, 0, 0);
		$db->expects(self::never())->method('query');

		$adapter = $this->createAdapter($db);
		$adapter->nonQuery("INSERT INTO example_table (name) VALUES ('test')");
		self::assertSame(123, $adapter->insertId());

		$adapter->nonQuery("UPDATE example_table SET name = 'updated'");
		self::assertSame(2, $adapter->affectedRows());
		self::assertSame(0, $adapter->insertId());

		$adapter->nonQuery('DELETE FROM example_table WHERE id = 123');
		self::assertSame(1, $adapter->affectedRows());
		self::assertSame(0, $adapter->insertId());
	}

	public function testFailedWriteResetsStatementStateAndRemembersError(): void {
		$error = new RuntimeException('Database write failed.', 1234);
		$db = $this->createMock(ilDBPdoInterface::class);
		$db->expects(self::once())->method('manipulate')->willThrowException($error);
		$db->expects(self::never())->method('getLastInsertId');
		$db->expects(self::never())->method('query');

		$adapter = $this->createAdapter($db);

		try {
			$adapter->nonQuery("INSERT INTO example_table (name) VALUES ('test')");
			self::fail('Expected a database exception.');
		} catch(RuntimeException $caught) {
			self::assertSame($error, $caught);
		}

		self::assertSame(0, $adapter->affectedRows());
		self::assertSame(0, $adapter->insertId());
		self::assertTrue($adapter->isError());
		self::assertSame(1234, $adapter->errorNumber());
		self::assertSame('Database write failed.', $adapter->errorMessage());
	}

	private function createAdapter(ilDBPdoInterface $db): Base3IliasDatabase {
		$GLOBALS['DIC'] = new class($db) {

			public function __construct(private readonly ilDBPdoInterface $database) {}

			public function database(): ilDBPdoInterface {
				return $this->database;
			}
		};

		return new Base3IliasDatabase();
	}
}
