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


namespace Base3\Base3Ilias\Test\Endpoint;

use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\Base3Ilias\Endpoint\Base3IliasCheck;
use PHPUnit\Framework\TestCase;

final class Base3IliasCheckTest extends TestCase {

	public function testDatabaseHealthOverviewUsesAllNativeReadOnlyQueries(): void {
		$sqls = [];
		$db = $this->createMock(IDatabase::class);
		$db->expects(self::exactly(14))->method('scalarQuery')->willReturnCallback(
			static function(string $sql) use (&$sqls): mixed {
				$sqls[] = $sql;
				return count($sqls) === 1 ? 'ilias_test' : count($sqls);
			}
		);
		$check = new Base3IliasCheck($db);
		$result = json_decode($check->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('base3iliascheck', $check::getName());
		self::assertSame('ilias_test', $result['database']);
		self::assertSame(2, $result['table_count']);
		self::assertSame(14, $result['trigger_count']);
		self::assertArrayHasKey('execution_time_seconds', $result);
		self::assertContains('SELECT DATABASE() AS current_database', $sqls);
		self::assertStringContainsString("table_schema = 'ilias_test'", $sqls[1]);
		self::assertSame('Base3 ILIAS Check', $check->getHelp());
	}
}
