<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Database\Api\IDatabase;
use Base3Ilias\Base3\Base3IliasStateStore;
use PHPUnit\Framework\TestCase;

final class Base3IliasStateStoreTest extends TestCase {

	public function testGetDecodesJsonAndInitializesStorageOnlyOnce(): void {
		$db = $this->createMock(IDatabase::class);
		$db->expects(self::exactly(2))->method('connect');
		$db->expects(self::once())->method('nonQuery')->with(self::stringContains('CREATE TABLE IF NOT EXISTS `custom_state`'));
		$db->method('escape')->willReturnArgument(0);
		$db->expects(self::exactly(2))->method('singleQuery')->willReturnOnConsecutiveCalls(
			['value' => '{"active":true,"count":3}', 'expires_at' => null],
			null
		);
		$store = new Base3IliasStateStore($db, 'custom_state');
		self::assertSame(['active' => true, 'count' => 3], $store->get('key'));
		self::assertSame('missing', $store->get('absent', 'missing'));
	}

	public function testInvalidJsonReturnsDefaultWithoutExposingBrokenValue(): void {
		$db = $this->database();
		$db->method('singleQuery')->willReturn(['value' => '{invalid', 'expires_at' => null]);
		self::assertSame(['safe' => true], (new Base3IliasStateStore($db))->get('key', ['safe' => true]));
	}

	public function testExpiredGetDeletesOldRowAndReturnsDefault(): void {
		$db = $this->database();
		$db->expects(self::exactly(2))->method('nonQuery')->willReturnCallback(static function(string $sql): void {
			self::assertTrue(str_contains($sql, 'CREATE TABLE IF NOT EXISTS') || str_contains($sql, 'DELETE FROM'));
		});
		$db->method('singleQuery')->willReturnOnConsecutiveCalls(
			['value' => '"old"', 'expires_at' => '2000-01-01 00:00:00'],
			['key' => 'expired'],
			null
		);
		self::assertSame('fallback', (new Base3IliasStateStore($db))->get('expired', 'fallback'));
	}

	public function testPresenceCheckDistinguishesActiveAndMissingRows(): void {
		$db = $this->database();
		$db->method('singleQuery')->willReturnOnConsecutiveCalls(
			['expires_at' => null], null,
			['expires_at' => 'invalid-date']
		);
		$store = new Base3IliasStateStore($db);
		self::assertTrue($store->has('active'));
		self::assertFalse($store->has('missing'));
		self::assertTrue($store->has('unparsable-date'));
	}

	public function testSetUpsertsEscapedJsonWithCorrectTtlAndCustomTable(): void {
		$queries = [];
		$db = $this->database(false);
		$db->method('escape')->willReturnCallback(static fn(string $s): string => str_replace("'", "\\'", $s));
		$db->method('nonQuery')->willReturnCallback(static function(string $sql) use (&$queries): void { $queries[] = $sql; });
		$store = new Base3IliasStateStore($db, 'user_state');
		$store->set("user's", ['name' => "O'Neil"], 15);
		$store->set('expired', null, 0);
		$store->set('persistent', false);
		self::assertCount(4, $queries);
		self::assertStringContainsString('`user_state`', $queries[0]);
		self::assertStringContainsString("user\\'s", $queries[1]);
		self::assertStringContainsString('DATE_ADD(NOW(), INTERVAL 15 SECOND)', $queries[1]);
		self::assertStringContainsString('NOW(), NOW()', $queries[2]);
		self::assertStringContainsString('NULL)', $queries[3]);
		self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $queries[1]);
	}

	public function testSetIfNotExistsNeverReplacesActiveValue(): void {
		$db = $this->database();
		$db->expects(self::once())->method('nonQuery')->with(self::stringContains('CREATE TABLE'));
		$db->method('singleQuery')->willReturn(['expires_at' => null]);
		self::assertFalse((new Base3IliasStateStore($db))->setIfNotExists('key', 'new'));
	}

	public function testSetIfNotExistsInsertsMissingAndUpdatesExpiredValue(): void {
		$queries = [];
		$db = $this->database();
		$db->method('singleQuery')->willReturnOnConsecutiveCalls(null, ['expires_at' => '2000-01-01 00:00:00']);
		$db->method('nonQuery')->willReturnCallback(static function(string $sql) use (&$queries): void { $queries[] = $sql; });
		$store = new Base3IliasStateStore($db);
		self::assertTrue($store->setIfNotExists('new', ['x' => 1]));
		self::assertTrue($store->setIfNotExists('expired', ['x' => 2], 30));
		self::assertCount(3, $queries);
		self::assertStringStartsWith('INSERT INTO', trim($queries[1]));
		self::assertStringStartsWith('UPDATE', trim($queries[2]));
		self::assertStringContainsString('INTERVAL 30 SECOND', $queries[2]);
	}

	public function testDeleteReportsMissingAndSuccessfulRemoval(): void {
		$db = $this->database();
		$db->method('singleQuery')->willReturnOnConsecutiveCalls(null, ['key' => 'present'], null);
		$db->method('isError')->willReturn(false);
		$db->expects(self::exactly(2))->method('nonQuery')->willReturnCallback(static function(string $sql): void {
			self::assertTrue(str_contains($sql, 'CREATE TABLE') || str_contains($sql, 'DELETE FROM'));
		});
		$store = new Base3IliasStateStore($db);
		self::assertFalse($store->delete('missing'));
		self::assertTrue($store->delete('present'));
	}

	public function testDeleteFailsIfBackendReportsError(): void {
		$db = $this->database();
		$db->method('singleQuery')->willReturn(['key' => 'present']);
		$db->method('isError')->willReturn(true);
		self::assertFalse((new Base3IliasStateStore($db))->delete('present'));
	}

	public function testListKeysReturnsBackendResultsAndFlushDoesNotDeleteData(): void {
		$db = $this->database();
		$db->expects(self::once())->method('listQuery')->with(self::callback(static fn(string $sql): bool =>
			str_contains($sql, "LIKE 'job.%'") && str_contains($sql, 'ORDER BY `key` ASC')
		))->willReturn([['key' => 'job.1'], ['key' => 'job.2']]);
		$store = new Base3IliasStateStore($db);
		self::assertSame([['key' => 'job.1'], ['key' => 'job.2']], $store->listKeys('job.'));
		$store->flush();
	}

	public function testUnserializableInputIsRecordedWithoutThrowing(): void {
		$queries = [];
		$db = $this->database();
		$db->method('nonQuery')->willReturnCallback(static function(string $sql) use (&$queries): void { $queries[] = $sql; });
		(new Base3IliasStateStore($db))->set('invalid', INF);
		self::assertStringContainsString('json_encode_failed', $queries[1]);
	}

	private function database(bool $useIdentityEscaping = true): IDatabase {
		$db = $this->createMock(IDatabase::class);
		if ($useIdentityEscaping) $db->method('escape')->willReturnArgument(0);
		return $db;
	}
}
