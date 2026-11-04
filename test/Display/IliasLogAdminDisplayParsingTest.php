<?php declare(strict_types=1);

namespace Base3Ilias\Test\Display;

use Base3Ilias\Display\IliasLogAdminDisplay;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class IliasLogAdminDisplayParsingTest extends TestCase {

	public function testRecognizesStructuredRequestTimestampChannelLevelAndMessage(): void {
		$line = '[request-12] [2026-11-04 20:00:00] ilias.ERROR: A failure occurred';
		self::assertSame([
			'request' => 'request-12',
			'timestamp' => '2026-11-04 20:00:00',
			'channel' => 'ilias',
			'level' => 'error',
			'message' => 'A failure occurred',
			'raw' => $line
		], $this->invoke('parseLogLine', $line));
	}

	public function testUnstructuredLinesRetainTheirOriginalContent(): void {
		$line = 'An unknown message: with symbols [ ]';
		self::assertSame([
			'request' => '', 'timestamp' => '', 'channel' => '', 'level' => '',
			'message' => $line, 'raw' => $line
		], $this->invoke('parseLogLine', $line));
	}

	public function testReadsOnlyRequestedTailLinesAcrossMultipleReadBlocks(): void {
		$file = tempnam(sys_get_temp_dir(), 'b3i-log-');
		self::assertIsString($file);
		try {
			$lines = [];
			for ($i = 0; $i < 1300; $i++) $lines[] = 'entry-' . $i . '-' . str_repeat('x', 15);
			file_put_contents($file, implode("\r\n", $lines) . "\r\n");
			self::assertSame(array_slice($lines, -5), $this->invoke('readTailLines', $file, 5));
		} finally {
			unlink($file);
		}
	}

	public function testEmptyLogFileReturnsNoLines(): void {
		$file = tempnam(sys_get_temp_dir(), 'b3i-log-');
		self::assertIsString($file);
		try {
			self::assertSame([], $this->invoke('readTailLines', $file, 100));
		} finally {
			unlink($file);
		}
	}

	public function testJsonResponsesPreserveStatusAndPayload(): void {
		$success = json_decode($this->invoke('jsonSuccess', ['logs' => ['hello']]), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('ok', $success['status']);
		self::assertSame(['logs' => ['hello']], $success['data']);
		self::assertNotSame('', $success['timestamp']);

		$error = json_decode($this->invoke('jsonError', 'not allowed'), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('error', $error['status']);
		self::assertSame('not allowed', $error['message']);
	}


	public function testReadTailLinesIgnoresEmptyLinesAndReturnsOnlyTheEnd(): void {
		$file = tempnam(sys_get_temp_dir(), 'b3i-log-lines-');
		self::assertIsString($file);
		try {
			file_put_contents($file, "\nfirst\n\nsecond\r\nthird\n");
			self::assertSame(['second', 'third'], $this->invoke('readTailLines', $file, 2));
			self::assertSame(['first', 'second', 'third'], $this->invoke('readTailLines', $file, 20));
		} finally {
			unlink($file);
		}
	}

	public function testJsonUnknownActionIsStructuredAndDoesNotReadAnyLogFile(): void {
		$request = $this->createMock(\Base3\Api\IRequest::class);
		$request->method('get')->with('action')->willReturn('unknown');
		$display = (new ReflectionClass(IliasLogAdminDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasLogAdminDisplay::class, 'request'))->setValue($display, $request);
		$output = (new ReflectionMethod(IliasLogAdminDisplay::class, 'handleJson'))->invoke($display);
		$json = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('error', $json['status']);
		self::assertStringContainsString('unknown', $json['message']);
	}

	public function testLogEndpointUsesJsonAndAnEmptyActionPlaceholder(): void {
		$link = $this->createMock(\Base3\LinkTarget\Api\ILinkTargetService::class);
		$link->expects(self::once())->method('getLink')->with(
			['name' => IliasLogAdminDisplay::getName(), 'out' => 'json'],
			['action' => '']
		)->willReturn('/logs?out=json');
		$display = (new ReflectionClass(IliasLogAdminDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasLogAdminDisplay::class, 'linkTargetService'))->setValue($display, $link);
		self::assertSame('/logs?out=json', (new ReflectionMethod(IliasLogAdminDisplay::class, 'buildEndpointBase'))->invoke($display));
	}

	public function testJsonTailSupportsLineLimitsAndParsesNewestRecordsFirst(): void {
		if (!class_exists(\ilIniFile::class)) {
			self::markTestSkipped('Native ILIAS INI service is unavailable outside ILIAS.');
		}
		$file = tempnam(sys_get_temp_dir(), 'b3i-log-tail-');
		self::assertIsString($file);
		file_put_contents($file, "first\nsecond\nthird\n");
		$ini = $this->createMock(\ilIniFile::class);
		$ini->method('readVariable')->willReturnCallback(static fn(string $section, string $key): string => match ($key) {
			'path' => dirname($file),
			'file' => basename($file),
			default => '',
		});
		$request = $this->createMock(\Base3\Api\IRequest::class);
		$num = 2;
		$request->method('get')->willReturnCallback(static function(string $key, mixed $default = null) use (&$num): mixed {
			return match ($key) {
				'action' => 'tail',
				'num' => $num,
				default => $default,
			};
		});
		$display = (new ReflectionClass(IliasLogAdminDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasLogAdminDisplay::class, 'request'))->setValue($display, $request);
		(new \ReflectionProperty(IliasLogAdminDisplay::class, 'ilIliasIniFile'))->setValue($display, $ini);
		try {
			$result = json_decode((new ReflectionMethod(IliasLogAdminDisplay::class, 'handleJson'))->invoke($display), true, 512, JSON_THROW_ON_ERROR);
			self::assertSame('ok', $result['status']);
			self::assertSame(2, $result['data']['num']);
			self::assertSame(['third', 'second'], array_column($result['data']['logs'], 'message'));
			$num = 0;
			$minimal = json_decode((new ReflectionMethod(IliasLogAdminDisplay::class, 'handleJson'))->invoke($display), true, 512, JSON_THROW_ON_ERROR);
			self::assertSame(1, $minimal['data']['num']);
			self::assertSame(['third'], array_column($minimal['data']['logs'], 'message'));
			$num = 100000;
			$maximal = json_decode((new ReflectionMethod(IliasLogAdminDisplay::class, 'handleJson'))->invoke($display), true, 512, JSON_THROW_ON_ERROR);
			self::assertSame(1000, $maximal['data']['num']);
		} finally {
			unlink($file);
		}
	}

	private function invoke(string $method, mixed ...$args): mixed {
		$instance = (new ReflectionClass(IliasLogAdminDisplay::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(IliasLogAdminDisplay::class, $method))->invoke($instance, ...$args);
	}
}
