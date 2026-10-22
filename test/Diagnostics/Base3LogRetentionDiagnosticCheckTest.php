<?php declare(strict_types=1);

namespace Base3Ilias\Test\Diagnostics;

use Base3\Configuration\Api\IConfiguration;
use Base3\Database\Api\IDatabase;
use Base3\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\State\Api\IStateStore;
use Base3Ilias\Diagnostics\Base3LogRetentionDiagnosticCheck;
use Base3Ilias\Job\Base3LogRetention;
use PHPUnit\Framework\TestCase;

final class Base3LogRetentionDiagnosticCheckTest extends TestCase {

	public function testAbsentTableIsNotApplicable(): void {
		$db = $this->createMock(IDatabase::class);
		$db->method('connected')->willReturn(true);
		$db->method('singleQuery')->willReturn(null);
		$db->expects($this->never())->method('scalarQuery');
		$this->assertSame(DiagnosticCheckResult::NOT_APPLICABLE, $this->makeCheck($db)->run()->status);
	}

	public function testActiveCleanupWithNoOverdueRowsPasses(): void {
		$db = $this->makeDatabase(0);
		$result = $this->makeCheck($db, true)->run();
		$this->assertSame(DiagnosticCheckResult::PASSED, $result->status);
		$this->assertSame(48, $result->details['retentionHours']);
		$this->assertSame(24, $result->details['graceHours']);
	}

	public function testOverdueRowsFailAndDisabledJobAlsoFails(): void {
		$failure = $this->makeCheck($this->makeDatabase(3), true)->run();
		$this->assertSame(DiagnosticCheckResult::FAILED, $failure->status);
		$this->assertSame(3, $failure->details['overdue']);
		$disabled = $this->makeCheck($this->makeDatabase(0), false)->run();
		$this->assertSame(DiagnosticCheckResult::FAILED, $disabled->status);
		$this->assertSame('cleanup_disabled', $disabled->messageKey);
	}

	private function makeDatabase(int $rows): IDatabase {
		$db = $this->createMock(IDatabase::class);
		$db->method('connected')->willReturn(true);
		$db->method('singleQuery')->willReturn(['table' => Base3LogRetention::TABLE]);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => $value);
		$db->expects($this->once())->method('scalarQuery')->with($this->callback(
			static fn(string $sql): bool => str_contains($sql, 'SELECT COUNT(*) FROM `base3_log`') && str_contains($sql, '`timestamp` <')
		))->willReturn($rows);
		return $db;
	}

	private function makeCheck(IDatabase $db, bool $enabled = false): Base3LogRetentionDiagnosticCheck {
		$configuration = $this->createMock(IConfiguration::class);
		$configuration->method('get')->willReturn(['base3logcleanupjob.active' => $enabled ? 1 : 0]);
		$state = $this->createMock(IStateStore::class);
		$state->method('get')->willReturn(48);
		return new Base3LogRetentionDiagnosticCheck($db, $configuration, $state);
	}

}
