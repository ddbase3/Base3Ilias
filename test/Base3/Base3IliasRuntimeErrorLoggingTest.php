<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use PHPUnit\Framework\TestCase;

final class Base3IliasRuntimeErrorLoggingTest extends TestCase {

	public function testUncaughtFatalErrorIsLoggedWithDebugDisabled(): void {
		$runtimeFile = dirname(__DIR__, 2) . '/src/Base3/Base3IliasRuntime.php';
		$errorLog = tempnam(sys_get_temp_dir(), 'base3-error-log-');
		$probeFile = tempnam(sys_get_temp_dir(), 'base3-error-probe-');
		self::assertNotFalse($errorLog);
		self::assertNotFalse($probeFile);

		try {
			$probe = '<?php declare(strict_types=1);' . "\n"
				. 'putenv("DEBUG=0");' . "\n"
				. 'require ' . var_export($runtimeFile, true) . ';' . "\n"
				. '$method = new ReflectionMethod(\\Base3Ilias\\Base3\\Base3IliasRuntime::class, "configureDebug");' . "\n"
				. '$method->invoke(null);' . "\n"
				. 'if ((error_reporting() & E_ERROR) === 0 || ini_get("display_errors") !== "0" || ini_get("log_errors") !== "1") exit(99);' . "\n"
				. 'throw new RuntimeException("base3 fatal logging regression");' . "\n";
			file_put_contents($probeFile, $probe);

			$command = escapeshellarg(PHP_BINARY)
				. ' -d log_errors=0 -d display_errors=1 -d error_log=' . escapeshellarg($errorLog)
				. ' ' . escapeshellarg($probeFile) . ' 2>&1';
			exec($command, $output, $exitCode);
			self::assertNotSame(99, $exitCode, implode("\n", $output));
			self::assertNotSame(0, $exitCode, 'The regression probe must throw an exception.');
			self::assertStringContainsString('base3 fatal logging regression', (string)file_get_contents($errorLog));
		}
		finally {
			@unlink($probeFile);
			@unlink($errorLog);
		}
	}
}
