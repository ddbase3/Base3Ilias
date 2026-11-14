<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Base3Ilias for BASE3 Framework.
 *
 * Base3Ilias integrates BASE3 with the ILIAS learning platform.
 * It provides native identity, services, assets and lifecycle adapters.
 * Application modules use the shared BASE3 Foundation contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0-or-later
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 ***********************************************************************/


namespace Base3\Base3Ilias\Test\Base3;

use Base3\Base3Ilias\Base3\Base3IliasDatabase;
use ilDBPdoInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;


require_once __DIR__ . '/Base3IliasDatabaseTestFixture1.php';
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
		$GLOBALS['DIC'] = new Base3IliasDatabaseTestFixture1($db);

		return new Base3IliasDatabase();
	}
}
