<?php declare(strict_types=1);

namespace Base3Ilias\Test\Display;

use Base3Ilias\Display\IliasErrorLogAdminDisplay;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class IliasErrorLogAdminDisplayFilesTest extends TestCase {

	public function testOnlyFilesBelowTheConfiguredRealDirectoryAreAccepted(): void {
		$root = $this->createDirectory();
		mkdir($root . '/logs');
		mkdir($root . '/logs-neighbor');
		file_put_contents($root . '/logs/inside.log', 'inside');
		file_put_contents($root . '/logs-neighbor/outside.log', 'outside');
		try {
			self::assertTrue($this->invoke('isSafeFile', $root . '/logs', $root . '/logs/inside.log'));
			self::assertFalse($this->invoke('isSafeFile', $root . '/logs', $root . '/logs-neighbor/outside.log'));
			self::assertFalse($this->invoke('isSafeFile', $root . '/logs', $root . '/logs/missing.log'));
		} finally {
			$this->removeDirectory($root);
		}
	}

	public function testReadsTheEndOfLongFilesWithoutReturningEarlierBytes(): void {
		$file = tempnam(sys_get_temp_dir(), 'b3i-error-');
		self::assertIsString($file);
		try {
			$content = str_repeat('A', 18000) . 'LAST RECORD';
			file_put_contents($file, $content);
			self::assertSame(substr($content, -256), $this->invoke('readFileEnd', $file, 256));
			self::assertSame($content, $this->invoke('readFileEnd', $file, strlen($content) + 5));
		} finally {
			unlink($file);
		}
	}

	public function testEmptyErrorLogAndZeroReadLimitReturnNoData(): void {
		$file = tempnam(sys_get_temp_dir(), 'b3i-error-');
		self::assertIsString($file);
		try {
			self::assertSame('', $this->invoke('readFileEnd', $file, 1024));
			file_put_contents($file, 'message');
			self::assertSame('', $this->invoke('readFileEnd', $file, 0));
		} finally {
			unlink($file);
		}
	}

	public function testListsOnlyFilesAndOrdersNewestFirst(): void {
		$root = $this->createDirectory();
		try {
			file_put_contents($root . '/old.log', 'old');
			file_put_contents($root . '/new.log', 'new');
			mkdir($root . '/subdirectory');
			$now = time();
			touch($root . '/old.log', $now - 100);
			touch($root . '/new.log', $now);
			clearstatcache();
			$files = $this->invoke('getFiles', $root);
			self::assertSame(['new.log', 'old.log'], array_column($files, 'name'));
			self::assertSame([3, 3], array_column($files, 'size'));
			foreach ($files as $file) {
				self::assertArrayNotHasKey('mtime_sort', $file);
				self::assertTrue($file['readable']);
			}
		} finally {
			$this->removeDirectory($root);
		}
	}

	public function testFormatsSizesAtEachUnitBoundary(): void {
		self::assertSame('0 B', $this->invoke('formatBytes', 0));
		self::assertSame('1023 B', $this->invoke('formatBytes', 1023));
		self::assertSame('1 KB', $this->invoke('formatBytes', 1024));
		self::assertSame('1 MB', $this->invoke('formatBytes', 1048576));
		self::assertSame('1 GB', $this->invoke('formatBytes', 1073741824));
	}


	public function testLogFileReadingNeverFollowsSymlinkOutsideConfiguredDirectory(): void {
		if (!function_exists('symlink')) {
			self::markTestSkipped('Symbolic links are unavailable on this platform.');
		}
		$root = $this->createDirectory();
		$outside = tempnam(sys_get_temp_dir(), 'b3i-outside-error-');
		self::assertIsString($outside);
		file_put_contents($outside, 'external secret');
		try {
			if (!@symlink($outside, $root . '/linked.log')) {
				self::markTestSkipped('Symbolic links cannot be created in the test directory.');
			}
			self::assertFalse($this->invoke('isSafeFile', $root, $root . '/linked.log'));
		} finally {
			@unlink($root . '/linked.log');
			@unlink($outside);
			$this->removeDirectory($root);
		}
	}

	public function testJoinPathAndJsonHelpersPreserveStructuredResponses(): void {
		self::assertSame('', $this->invoke('joinPath', '', ''));
		self::assertSame('file.log', $this->invoke('joinPath', '', ' file.log '));
		self::assertSame('/srv/log', $this->invoke('joinPath', '/srv/log', ''));
		self::assertSame('/srv/log/file.log', $this->invoke('joinPath', '/srv/log/', '/file.log'));
		$success = json_decode($this->invoke('jsonSuccess', ['files' => ['log']]), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('ok', $success['status']);
		self::assertSame(['files' => ['log']], $success['data']);
		$error = json_decode($this->invoke('jsonError', 'Invalid selection'), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('error', $error['status']);
		self::assertSame('Invalid selection', $error['message']);
	}

	public function testUnknownJsonActionIsRejectedWithoutReadingConfiguredFiles(): void {
		$request = $this->createMock(\Base3\Api\IRequest::class);
		$request->method('get')->with('action')->willReturn('unrecognized');
		$display = (new ReflectionClass(IliasErrorLogAdminDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasErrorLogAdminDisplay::class, 'request'))->setValue($display, $request);
		$response = json_decode((new ReflectionMethod(IliasErrorLogAdminDisplay::class, 'handleJson'))->invoke($display), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('error', $response['status']);
		self::assertStringContainsString('unrecognized', $response['message']);
	}

	public function testErrorLogEndpointUsesJsonAndAnEmptyActionPlaceholder(): void {
		$link = $this->createMock(\Base3\LinkTarget\Api\ILinkTargetService::class);
		$link->expects(self::once())->method('getLink')->with(
			['name' => IliasErrorLogAdminDisplay::getName(), 'out' => 'json'],
			['action' => '']
		)->willReturn('/errors?out=json');
		$display = (new ReflectionClass(IliasErrorLogAdminDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasErrorLogAdminDisplay::class, 'linkTargetService'))->setValue($display, $link);
		self::assertSame('/errors?out=json', (new ReflectionMethod(IliasErrorLogAdminDisplay::class, 'buildEndpointBase'))->invoke($display));
	}

	public function testErrorLogJsonListAndReadUseConfiguredDirectoryAndLimitEntries(): void {
		if (!class_exists(\ilIniFile::class)) {
			self::markTestSkipped('Native ILIAS INI service is unavailable outside ILIAS.');
		}
		$root = $this->createDirectory();
		file_put_contents($root . '/old.log', 'old content');
		file_put_contents($root . '/new.log', 'new content');
		touch($root . '/old.log', 1609459200);
		touch($root . '/new.log', 1609545600);
		clearstatcache();
		$ini = $this->createMock(\ilIniFile::class);
		$ini->method('readVariable')->willReturnCallback(static fn(string $section, string $key): string => $root);
		$action = 'list';
		$num = 1;
		$file = 'new.log';
		$request = $this->createMock(\Base3\Api\IRequest::class);
		$request->method('get')->willReturnCallback(static function(string $key, mixed $default = null) use (&$action, &$num, &$file): mixed {
			return match ($key) {
				'action' => $action,
				'num' => $num,
				'file' => $file,
				default => $default,
			};
		});
		$display = (new ReflectionClass(IliasErrorLogAdminDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasErrorLogAdminDisplay::class, 'request'))->setValue($display, $request);
		(new \ReflectionProperty(IliasErrorLogAdminDisplay::class, 'ilIliasIniFile'))->setValue($display, $ini);
		$method = new ReflectionMethod(IliasErrorLogAdminDisplay::class, 'handleJson');
		try {
			$list = json_decode($method->invoke($display), true, 512, JSON_THROW_ON_ERROR);
			self::assertSame('ok', $list['status']);
			self::assertSame(1, $list['data']['num']);
			self::assertSame(['new.log'], array_column($list['data']['files'], 'name'));
			$action = 'read';
			$read = json_decode($method->invoke($display), true, 512, JSON_THROW_ON_ERROR);
			self::assertSame('new content', $read['data']['content']);
			self::assertSame(11, $read['data']['size']);
			$file = 'missing.log';
			$missing = json_decode($method->invoke($display), true, 512, JSON_THROW_ON_ERROR);
			self::assertFalse($missing['data']['readable']);
			self::assertSame('', $missing['data']['content']);
		} finally {
			$this->removeDirectory($root);
		}
	}

	private function invoke(string $method, mixed ...$args): mixed {
		$instance = (new ReflectionClass(IliasErrorLogAdminDisplay::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(IliasErrorLogAdminDisplay::class, $method))->invoke($instance, ...$args);
	}

	private function createDirectory(): string {
		$path = sys_get_temp_dir() . '/base3ilias-log-' . bin2hex(random_bytes(8));
		mkdir($path, 0700);
		return $path;
	}

	private function removeDirectory(string $path): void {
		foreach (scandir($path) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') continue;
			$full = $path . '/' . $entry;
			if (is_dir($full)) $this->removeDirectory($full);
			else unlink($full);
		}
		rmdir($path);
	}
}
