<?php declare(strict_types=1);

namespace Base3Ilias\Test\Job;

use Base3\Configuration\Api\IConfiguration;
use Base3\Database\Api\IDatabase;
use Base3\State\Api\IStateStore;
use Base3\Worker\Api\IJobExecutionPolicy;
use Base3Ilias\Job\Base3LogCleanupJob;
use PHPUnit\Framework\TestCase;

final class Base3LogCleanupJobTest extends TestCase {

	public function testSchedulingAndEnablementReadTheSingleJobConfig(): void {
		$db = $this->createMock(IDatabase::class);
		$config = $this->createMock(IConfiguration::class);
		$config->expects(self::once())->method('get')->with('job')->willReturn([
			'base3logcleanupjob.active' => '1',
			'base3logcleanupjob.priority' => '4'
		]);
		$job = new Base3LogCleanupJob($db, $config, $this->createMock(IStateStore::class));
		self::assertSame('base3logcleanupjob', $job::getName());
		self::assertTrue($job->isActive());
		self::assertSame(4, $job->getPriority());
		self::assertSame(['policy' => 'dailywindowjobpolicy', 'data' => ['from' => '02:00', 'to' => '04:00']], $job->getPolicyDefinition());
	}

	public function testDisconnectedDatabaseDoesNotAttemptCleanupOrMarkRun(): void {
		$db = $this->createMock(IDatabase::class);
		$db->expects(self::once())->method('connect');
		$db->method('connected')->willReturn(false);
		$db->expects(self::never())->method('nonQuery');
		$db->expects(self::never())->method('singleQuery');
		$job = $this->job($db);
		self::assertSame('DB not connected', $job->go());
	}

	public function testAbsentLogTableDoesNotDeleteAnything(): void {
		$db = $this->createMock(IDatabase::class);
		$db->method('connected')->willReturn(true);
		$db->expects(self::once())->method('singleQuery')->with("SHOW TABLES LIKE 'base3_log'")->willReturn(null);
		$db->expects(self::never())->method('nonQuery');
		self::assertSame('Skip (base3_log does not exist)', $this->job($db)->go());
	}

	public function testCleanupDeletesOnlyOldestBatchAndMarksPolicyAfterWrite(): void {
		$db = $this->createMock(IDatabase::class);
		$db->method('connected')->willReturn(true);
		$db->method('singleQuery')->with("SHOW TABLES LIKE 'base3_log'")->willReturn(['table' => 'base3_log']);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => $value);
		$db->expects(self::once())->method('nonQuery')->with(self::callback(static fn(string $sql): bool =>
			str_contains($sql, 'DELETE FROM base3_log')
			&& str_contains($sql, 'WHERE `timestamp` < ')
			&& str_contains($sql, 'ORDER BY id ASC')
			&& str_contains($sql, 'LIMIT 7')
		));
		$state = $this->createMock(IStateStore::class);
		$state->method('get')->willReturnCallback(static fn(string $key, mixed $default = null): mixed =>
			str_ends_with($key, 'retention_hours') ? 3 : 7
		);
		$job = $this->job($db, $state);
		$policy = $this->createMock(IJobExecutionPolicy::class);
		$policy->expects(self::once())->method('markRun')->with('base3logcleanupjob');
		$job->setExecutionPolicy($policy);
		self::assertStringStartsWith('Log cleanup done (cutoff: ', $job->go());
	}

	private function job(IDatabase $db, ?IStateStore $state = null): Base3LogCleanupJob {
		$configuration = $this->createMock(IConfiguration::class);
		return new Base3LogCleanupJob($db, $configuration, $state ?? $this->createMock(IStateStore::class));
	}
}
