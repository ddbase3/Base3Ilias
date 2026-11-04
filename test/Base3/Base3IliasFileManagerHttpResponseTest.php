<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasFileManagerHttpService;
use PHPUnit\Framework\TestCase;

final class Base3IliasFileManagerHttpResponseTest extends TestCase {

	public function testSuccessfulResponseIsSerializedAsJsonAndExits(): void {
		[$payload, $status] = $this->invokeResponse('return ["ok" => true, "path" => "a/b"];');
		self::assertSame(['ok' => true, 'path' => 'a/b'], $payload);
		self::assertSame(200, $status);
	}

	public function testInvalidRequestResponseUsesHttp400(): void {
		[$payload, $status] = $this->invokeResponse('throw new \\InvalidArgumentException("Invalid file name");');
		self::assertSame(['message' => 'Invalid file name'], $payload);
		self::assertSame(400, $status);
	}

	public function testMalformedJsonResponseUsesHttp400(): void {
		[$payload, $status] = $this->invokeResponse('throw new \\JsonException("Malformed JSON");');
		self::assertSame(['message' => 'Malformed JSON'], $payload);
		self::assertSame(400, $status);
	}

	public function testUnexpectedFailuresUseHttp500(): void {
		[$payload, $status] = $this->invokeResponse('throw new \\RuntimeException("Storage unavailable");');
		self::assertSame(['message' => 'Storage unavailable'], $payload);
		self::assertSame(500, $status);
	}

	public function testExistingErrorStatusIsNotSilentlyDowngraded(): void {
		[$payload, $status] = $this->invokeResponse('\\http_response_code(404); throw new \\RuntimeException("Missing resource");');
		self::assertSame(['message' => 'Missing resource'], $payload);
		self::assertSame(404, $status);
	}

	public function testInvalidPreviousStatusIsResetToHttp200OnSuccess(): void {
		[$payload, $status] = $this->invokeResponse('\\http_response_code(700); return ["ready" => true];');
		self::assertSame(['ready' => true], $payload);
		self::assertSame(200, $status);
	}

	private function invokeResponse(string $handlerBody): array {
		$file = realpath(dirname(__DIR__, 2) . '/src/Base3/Base3IliasFileManagerHttpService.php');
		self::assertNotFalse($file);

		$script = 'require_once ' . var_export($file, true) . ';'
			. '$subject = (new \\ReflectionClass(' . var_export(Base3IliasFileManagerHttpService::class, true) . '))->newInstanceWithoutConstructor();'
			. '\\register_shutdown_function(static function(): void { echo "\\nSTATUS=" . \\http_response_code(); });'
			. '$method = new \\ReflectionMethod(' . var_export(Base3IliasFileManagerHttpService::class, true) . ', "respondJson");'
			. '$method->invoke($subject, static function(): array {' . $handlerBody . '});';

		$lines = [];
		$status = 0;
		exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -r ' . escapeshellarg($script) . ' 2>&1', $lines, $status);
		self::assertSame(0, $status, implode("\n", $lines));
		$output = implode("\n", $lines);
		self::assertStringContainsString("\nSTATUS=", $output);
		[$json, $statusString] = explode("\nSTATUS=", $output, 2);
		self::assertIsArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
		return [json_decode($json, true, 512, JSON_THROW_ON_ERROR), (int) $statusString];
	}
}
