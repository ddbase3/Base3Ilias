<?php declare(strict_types=1);

namespace Base3Ilias\Test\Display;

use Base3Ilias\Display\IliasDashboardDisplay;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class IliasDashboardDisplaySummaryTest extends TestCase {

	public function testAggregatesCheckCountsAndRoundsAvailabilityScore(): void {
		$summary = $this->invoke('getSummary', [
			['status' => 'ok'],
			['status' => 'ok'],
			['status' => 'warning']
		]);
		self::assertSame('warning', $summary['status']);
		self::assertSame(2, $summary['ok']);
		self::assertSame(1, $summary['warning']);
		self::assertSame(0, $summary['error']);
		self::assertSame(3, $summary['total']);
		self::assertSame(67, $summary['score']);
	}

	public function testErrorsTakePriorityOverWarnings(): void {
		self::assertSame('error', $this->invoke('getWorstStatus', [
			['status' => 'warning'], ['status' => 'error'], ['status' => 'ok']
		]));
		self::assertSame('warning', $this->invoke('getWorstStatus', [['status' => 'warning']]));
		self::assertSame('ok', $this->invoke('getWorstStatus', [['status' => 'ok']]));
	}

	public function testEmptyChecksHaveZeroScoreAndNoErrors(): void {
		$summary = $this->invoke('getSummary', []);
		self::assertSame('ok', $summary['status']);
		self::assertSame(0, $summary['total']);
		self::assertSame(0, $summary['score']);
	}

	public function testFormatsFileSizesAtUnitBoundaries(): void {
		self::assertSame('0 B', $this->invoke('formatBytes', 0));
		self::assertSame('1 KB', $this->invoke('formatBytes', 1024));
		self::assertSame('1 MB', $this->invoke('formatBytes', 1048576));
		self::assertSame('1 GB', $this->invoke('formatBytes', 1073741824));
	}


	public function testPathResolutionHandlesConfiguredEmptyRelativeAndAbsolutePaths(): void {
		self::assertSame('', $this->invoke('resolvePath', '/srv/ilias', ''));
		self::assertSame('client', $this->invoke('resolvePath', '', 'client'));
		self::assertSame('/srv/ilias/client', $this->invoke('resolvePath', '/srv/ilias/', 'client'));
		self::assertSame('/private/client', $this->invoke('resolvePath', '/srv/ilias', '/private/client'));
		self::assertSame('C:\\ilias\\client', $this->invoke('resolvePath', '/srv/ilias', 'C:\\ilias\\client'));
		self::assertSame('/root', $this->invoke('joinPath', '/root', ''));
		self::assertSame('relative', $this->invoke('joinPath', '', 'relative'));
		self::assertSame('/root/relative', $this->invoke('joinPath', '/root/', '/relative'));
	}

	public function testDirectoryAndFileStatusRejectMissingAndAcceptExistingFixtures(): void {
		$root = sys_get_temp_dir() . '/base3ilias-dashboard-' . bin2hex(random_bytes(8));
		self::assertTrue(mkdir($root, 0700));
		$file = $root . '/application.log';
		try {
			self::assertSame('error', $this->invoke('directoryStatus', ''));
			self::assertSame('error', $this->invoke('directoryStatus', $root . '/absent'));
			self::assertSame('ok', $this->invoke('directoryStatus', $root));
			self::assertSame('error', $this->invoke('fileStatus', ''));
			self::assertSame('error', $this->invoke('fileStatus', $root . '/absent.log'));
			self::assertSame('error', $this->invoke('fileStatus', $root));
			self::assertSame('error', $this->invoke('directoryStatus', $file));
		} finally {
			@unlink($file);
			rmdir($root);
		}
	}

	public function testDashboardCheckRowsDistinguishMissingAndExistingPaths(): void {
		$root = sys_get_temp_dir() . '/base3ilias-dashboard-check-' . bin2hex(random_bytes(8));
		self::assertTrue(mkdir($root, 0700));
		$file = $root . '/log.txt';
		file_put_contents($file, 'diagnostic');
		try {
			self::assertSame('error', $this->invoke('checkDirectory', 'Root', '', true, true)['status']);
			self::assertSame('error', $this->invoke('checkDirectory', 'Root', $root . '/missing', true, true)['status']);
			self::assertSame('error', $this->invoke('checkDirectory', 'Root', $file, true, true)['status']);
			self::assertSame('ok', $this->invoke('checkDirectory', 'Root', $root, true, true)['status']);
			self::assertSame('error', $this->invoke('checkFile', 'Log', '', true, true)['status']);
			self::assertSame('error', $this->invoke('checkFile', 'Log', $root . '/missing', true, true)['status']);
			self::assertSame('error', $this->invoke('checkFile', 'Log', $root, true, true)['status']);
			self::assertSame('ok', $this->invoke('checkFile', 'Log', $file, true, true)['status']);
		} finally {
			unlink($file);
			rmdir($root);
		}
	}

	public function testLogCountersAndLatestFileOnlyIncludeFilesAndRespectMtime(): void {
		$root = sys_get_temp_dir() . '/base3ilias-dashboard-log-' . bin2hex(random_bytes(8));
		self::assertTrue(mkdir($root, 0700));
		try {
			self::assertSame(0, $this->invoke('errorLogCount', $root));
			self::assertSame('', $this->invoke('latestErrorLogFile', $root));
			self::assertSame('No files', $this->invoke('latestErrorLogMeta', $root));
			file_put_contents($root . '/old.log', 'old');
			file_put_contents($root . '/latest.log', 'latest');
			mkdir($root . '/subdirectory');
			touch($root . '/old.log', 1609459200);
			touch($root . '/latest.log', 1609545600);
			clearstatcache();
			self::assertSame(2, $this->invoke('errorLogCount', $root));
			self::assertSame('latest.log', $this->invoke('latestErrorLogFile', $root));
			self::assertStringContainsString('latest.log, modified ', $this->invoke('latestErrorLogMeta', $root));
			self::assertSame(0, $this->invoke('errorLogCount', $root . '/missing'));
			self::assertSame('', $this->invoke('latestErrorLogFile', $root . '/missing'));
		} finally {
			@unlink($root . '/old.log');
			@unlink($root . '/latest.log');
			@rmdir($root . '/subdirectory');
			rmdir($root);
		}
	}

	public function testFileMetadataAndLastModifiedValueRespectActualFileState(): void {
		$file = tempnam(sys_get_temp_dir(), 'b3i-dashboard-meta-');
		self::assertIsString($file);
		try {
			file_put_contents($file, str_repeat('a', 1024));
			touch($file, 1609459200);
			clearstatcache();
			self::assertStringContainsString('1 KB', $this->invoke('fileMeta', $file));
			self::assertStringContainsString('modified', $this->invoke('fileMeta', $file));
			self::assertNotSame('', $this->invoke('mtimeValue', $file));
			self::assertSame('Not found', $this->invoke('fileMeta', $file . '/absent'));
			self::assertSame('Not found', $this->invoke('mtimeValue', $file . '/absent'));
		} finally {
			unlink($file);
		}
	}

	public function testHealthTilesPreserveChecksWithoutAdditionalFields(): void {
		self::assertSame([
			['label' => 'One', 'status' => 'ok', 'message' => 'OK', 'path' => '/tmp/one'],
			['label' => 'Two', 'status' => 'warning', 'message' => 'Not writable', 'path' => '/tmp/two'],
		], $this->invoke('getPathCheckTiles', [
			['label' => 'One', 'status' => 'ok', 'message' => 'OK', 'path' => '/tmp/one', 'extra' => true],
			['label' => 'Two', 'status' => 'warning', 'message' => 'Not writable', 'path' => '/tmp/two'],
		]));
	}

	public function testFallbackLabelsAndValueFormattingHandleNonScalarValues(): void {
		self::assertSame('Unset', $this->invoke('safeValue', "  \t", 'Unset'));
		self::assertSame('selected', $this->invoke('safeValue', ' selected ', 'Unset'));
		self::assertSame('Not configured', $this->invoke('pathShortValue', ''));
		self::assertSame('log.txt', $this->invoke('pathShortValue', '/var/log/log.txt'));
		self::assertSame('', $this->invoke('formatValue', []));
		self::assertSame('', $this->invoke('formatValue', null));
		self::assertSame('yes', $this->invoke('formatValue', true));
		self::assertSame('no', $this->invoke('formatValue', false));
		self::assertSame('12', $this->invoke('formatValue', 12));
		self::assertSame(['value' => 'über'], json_decode($this->invoke('formatValue', ['value' => 'über']), true, 512, JSON_THROW_ON_ERROR));
		self::assertSame('stdClass', $this->invoke('formatValue', new \stdClass()));
		self::assertSame('warning', $this->invoke('getWorstStatus', [['status' => 'warning'], ['status' => 'unknown']]));
	}

	public function testQuickLinksUseTheExistingLinkTargetServiceContract(): void {
		$linkTarget = $this->createMock(\Base3\LinkTarget\Api\ILinkTargetService::class);
		$linkTarget->expects(self::exactly(7))->method('getLink')->willReturnCallback(
			static function(array $target, array $params = []): string {
				self::assertSame([], $params);
				self::assertSame('html', $target['out']);
				return '/display/' . $target['name'];
			}
		);
		$display = (new ReflectionClass(IliasDashboardDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasDashboardDisplay::class, 'linkTargetService'))->setValue($display, $linkTarget);
		$links = (new ReflectionMethod(IliasDashboardDisplay::class, 'getQuickLinks'))->invoke($display);
		self::assertCount(7, $links);
		self::assertSame(['config', 'health', 'log', 'errors', 'request', 'permission', 'object'], array_column($links, 'type'));
		foreach ($links as $link) {
			self::assertSame('/display/' . $link['command'], $link['url']);
		}
	}

	private function invoke(string $method, mixed ...$args): mixed {
		$instance = (new ReflectionClass(IliasDashboardDisplay::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(IliasDashboardDisplay::class, $method))->invoke($instance, ...$args);
	}
}
