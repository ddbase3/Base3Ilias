<?php declare(strict_types=1);

namespace Base3Ilias\Test\Display;

use Base3Ilias\Display\IliasSystemHealthDisplay;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class IliasSystemHealthDisplayPathsTest extends TestCase {

	public function testReportsWorstHealthStatusAcrossSections(): void {
		$sections = [
			['rows' => [['status' => 'ok'], ['status' => 'info']]],
			['rows' => [['status' => 'warning'], ['status' => 'error']]]
		];
		$summary = $this->invoke('getSummary', $sections);
		self::assertSame(['ok' => 1, 'warning' => 1, 'error' => 1, 'info' => 1, 'total' => 4, 'status' => 'error'], $summary);
	}

	public function testEmptyHealthSummaryRemainsOk(): void {
		self::assertSame([
			'ok' => 0, 'warning' => 0, 'error' => 0, 'info' => 0, 'total' => 0, 'status' => 'ok'
		], $this->invoke('getSummary', []));
	}

	public function testRelativePathsAreResolvedAgainstTheConfiguredBaseDirectory(): void {
		self::assertSame('/srv/ilias/data', $this->invoke('resolvePath', '/srv/ilias/', 'data'));
		self::assertSame('/var/log/ilias', $this->invoke('resolvePath', '/srv/ilias', '/var/log/ilias'));
		self::assertSame('relative', $this->invoke('resolvePath', '', 'relative'));
		self::assertSame('', $this->invoke('resolvePath', '/srv/ilias', ''));
	}

	public function testRecognizesWindowsAndUnixAbsolutePaths(): void {
		self::assertTrue($this->invoke('isAbsolutePath', '/var/log'));
		self::assertTrue($this->invoke('isAbsolutePath', 'C:\\ilias\\data'));
		self::assertFalse($this->invoke('isAbsolutePath', 'data/ilias'));
	}

	public function testFormatsLargeHealthValuesWithoutLosingUnits(): void {
		self::assertSame('512 B', $this->invoke('formatBytes', 512));
		self::assertSame('1 KB', $this->invoke('formatBytes', 1024));
		self::assertSame('1.5 MB', $this->invoke('formatBytes', 1572864));
	}


	public function testDirectoryCheckDistinguishesOptionalMissingAndInvalidPaths(): void {
		$root = sys_get_temp_dir() . '/base3ilias-health-' . bin2hex(random_bytes(8));
		self::assertTrue(mkdir($root, 0700));
		$file = $root . '/config.ini';
		file_put_contents($file, 'configured');
		try {
			self::assertSame('info', $this->invoke('checkDirectory', 'Optional', 'config', '', false, true, false)['status']);
			self::assertSame('error', $this->invoke('checkDirectory', 'Root', 'config', '', true, true, false)['status']);
			self::assertSame('error', $this->invoke('checkDirectory', 'Root', 'config', $root . '/missing', true, true, false)['status']);
			self::assertSame('error', $this->invoke('checkDirectory', 'Root', 'config', $file, true, true, false)['status']);
			$found = $this->invoke('checkDirectory', 'Root', 'config', $root, true, true, false);
			self::assertSame('ok', $found['status']);
			self::assertSame('directory', $found['type']);
			self::assertNotEmpty($found['meta']);
		} finally {
			unlink($file);
			rmdir($root);
		}
	}

	public function testFileCheckDistinguishesEmptyMissingDirectoryAndRealFile(): void {
		$root = sys_get_temp_dir() . '/base3ilias-health-file-' . bin2hex(random_bytes(8));
		self::assertTrue(mkdir($root, 0700));
		$file = $root . '/config.ini';
		file_put_contents($file, 'configured');
		try {
			self::assertSame('info', $this->invoke('checkFile', 'Optional', 'config', '', false, true, false)['status']);
			self::assertSame('error', $this->invoke('checkFile', 'Required', 'config', '', true, true, false)['status']);
			self::assertSame('error', $this->invoke('checkFile', 'Required', 'config', $root . '/missing', true, true, false)['status']);
			self::assertSame('error', $this->invoke('checkFile', 'Required', 'config', $root, true, true, false)['status']);
			$found = $this->invoke('checkFile', 'Required', 'config', $file, true, true, false);
			self::assertSame('ok', $found['status']);
			self::assertSame('file', $found['type']);
			self::assertContains('Size', array_column($found['meta'], 'label'));
		} finally {
			unlink($file);
			rmdir($root);
		}
	}

	public function testExecutableChecksDistinguishOptionalUnconfiguredMissingNonExecutableAndAvailable(): void {
		$root = sys_get_temp_dir() . '/base3ilias-health-executable-' . bin2hex(random_bytes(8));
		self::assertTrue(mkdir($root, 0700));
		$file = $root . '/bin';
		file_put_contents($file, '#!/bin/sh');
		chmod($file, 0600);
		try {
			self::assertSame('info', $this->invoke('checkExecutable', 'Optional', 'tools', '', false)['status']);
			self::assertSame('error', $this->invoke('checkExecutable', 'Required', 'tools', '', true)['status']);
			self::assertSame('warning', $this->invoke('checkExecutable', 'Tool', 'tools', $root . '/missing', false)['status']);
			self::assertSame('warning', $this->invoke('checkExecutable', 'Tool', 'tools', $root, false)['status']);
			self::assertSame('warning', $this->invoke('checkExecutable', 'Tool', 'tools', $file, false)['status']);
			self::assertSame('ok', $this->invoke('checkExecutable', 'PHP', 'tools', PHP_BINARY, false)['status']);
		} finally {
			unlink($file);
			rmdir($root);
		}
	}

	public function testFileMetadataIncludesSizeAndFilesystemOwnershipWithoutExposingFileContents(): void {
		$file = tempnam(sys_get_temp_dir(), 'b3i-health-meta-');
		self::assertIsString($file);
		try {
			file_put_contents($file, 'sensitive content');
			$meta = $this->invoke('getPathMeta', $file);
			self::assertSame([], $this->invoke('getPathMeta', $file . '.missing'));
			self::assertContains('Permissions', array_column($meta, 'label'));
			self::assertContains('Size', array_column($meta, 'label'));
			self::assertNotContains('sensitive content', array_column($meta, 'value'));
		} finally {
			unlink($file);
		}
	}

	public function testHealthSummaryIncludesUnknownRowsWithoutCountingThemAsKnownStatus(): void {
		$summary = $this->invoke('getSummary', [
			['rows' => [['status' => 'unknown'], ['status' => 'ok']]],
			['rows' => [['status' => 'warning']]]
		]);
		self::assertSame(3, $summary['total']);
		self::assertSame(1, $summary['ok']);
		self::assertSame(1, $summary['warning']);
		self::assertSame(0, $summary['error']);
		self::assertSame('warning', $summary['status']);
	}

	public function testHealthPathJoinHandlesBlankAndSeparators(): void {
		self::assertSame('', $this->invoke('joinPath', '', ''));
		self::assertSame('path', $this->invoke('joinPath', '', ' path '));
		self::assertSame('/root/', $this->invoke('joinPath', ' /root/ ', ''));
		self::assertSame('/root/child', $this->invoke('joinPath', '/root/', '/child'));
		self::assertSame('/root', $this->invoke('resolvePath', '', '/root'));
		self::assertSame('C:\\ilias', $this->invoke('resolvePath', '/other', 'C:\\ilias'));
	}

	private function invoke(string $method, mixed ...$args): mixed {
		$instance = (new ReflectionClass(IliasSystemHealthDisplay::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(IliasSystemHealthDisplay::class, $method))->invoke($instance, ...$args);
	}
}
