<?php declare(strict_types=1);

namespace Base3Ilias\Test\Job;

use Base3\Database\Api\IDatabase;
use Base3\State\Api\IStateStore;
use Base3Ilias\Job\Base3LogRetention;
use PHPUnit\Framework\TestCase;

final class Base3LogRetentionTest extends TestCase {

	public function testCleanupActivationIsExplicitAndNoImplicitDefault(): void {
		self::assertFalse(Base3LogRetention::isCleanupActive([]));
		self::assertFalse(Base3LogRetention::isCleanupActive(['base3logcleanupjob.active' => 0]));
		self::assertFalse(Base3LogRetention::isCleanupActive(['base3logcleanupjob.active' => 2]));
		self::assertTrue(Base3LogRetention::isCleanupActive(['base3logcleanupjob.active' => 1]));
	}

	public function testRetentionAndBatchUsePositiveConfiguredValuesOrDefaults(): void {
		$state = $this->createMock(IStateStore::class);
		$state->method('get')->willReturnCallback(static fn(string $key, mixed $default = null): mixed =>
			str_ends_with($key, 'retention_hours') ? '72' : '15'
		);
		self::assertSame(72, Base3LogRetention::retentionHours($state));
		self::assertSame(15, Base3LogRetention::deleteBatch($state));

		$invalid = $this->createMock(IStateStore::class);
		$invalid->method('get')->willReturn(-3);
		self::assertSame(Base3LogRetention::DEFAULT_HOURS, Base3LogRetention::retentionHours($invalid));
		self::assertSame(Base3LogRetention::DEFAULT_BATCH, Base3LogRetention::deleteBatch($invalid));
	}

	public function testCutoffIsBasedOnRequestedHours(): void {
		$before = time() - 3600;
		$cutoff = strtotime(Base3LogRetention::cutoffSqlString(1));
		$after = time() - 3600;
		self::assertIsInt($cutoff);
		self::assertGreaterThanOrEqual($before, $cutoff);
		self::assertLessThanOrEqual($after, $cutoff);
	}

	public function testTableDiscoveryUsesExpectedSql(): void {
		$db = $this->createMock(IDatabase::class);
		$db->expects(self::exactly(2))->method('singleQuery')->with("SHOW TABLES LIKE 'base3_log'")
			->willReturnOnConsecutiveCalls(null, ['table' => 'base3_log']);
		self::assertFalse(Base3LogRetention::tableExists($db));
		self::assertTrue(Base3LogRetention::tableExists($db));
	}
}
