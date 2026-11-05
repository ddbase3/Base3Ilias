<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasRuntime;
use Base3Ilias\Base3\Base3IliasServiceLocator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

final class Base3IliasRuntimeEnvironmentTest extends TestCase {

	public function testMissingBootstrappedServiceLocatorIsReportedExplicitly(): void {
		$property = new ReflectionProperty(Base3IliasRuntime::class, 'serviceLocator');
		$original = $property->getValue();
		try {
			$property->setValue(null, null);
			$this->expectException(RuntimeException::class);
			$this->expectExceptionMessage('runtime not booted');
			Base3IliasRuntime::getServiceLocator();
		} finally {
			$property->setValue(null, $original);
		}
	}

	public function testGetterReturnsExistingLocatorWithoutBootingTheHost(): void {
		$property = new ReflectionProperty(Base3IliasRuntime::class, 'serviceLocator');
		$original = $property->getValue();
		try {
			$locator = $this->createMock(Base3IliasServiceLocator::class);
			$property->setValue(null, $locator);
			self::assertSame($locator, Base3IliasRuntime::getServiceLocator());
		} finally {
			$property->setValue(null, $original);
		}
	}

	public function testEnsureDirectoryRejectsEmptyPathAndCreatesOnlyTemporaryDirectory(): void {
		$method = new ReflectionMethod(Base3IliasRuntime::class, 'ensureDirectory');
		try {
			$method->invoke(null, '');
			self::fail('Empty directory path was accepted.');
		} catch (RuntimeException $error) {
			self::assertSame('Directory path is empty.', $error->getMessage());
		}

		$root = sys_get_temp_dir() . '/base3ilias-runtime-' . bin2hex(random_bytes(8));
		$nested = $root . '/one/two';
		try {
			$method->invoke(null, $nested);
			self::assertDirectoryExists($nested);
			$method->invoke(null, $nested);
			self::assertDirectoryExists($nested);
		} finally {
			if (is_dir($nested)) rmdir($nested);
			if (is_dir($root . '/one')) rmdir($root . '/one');
			if (is_dir($root)) rmdir($root);
		}
	}

	public function testCliRequestDefaultsPreserveExistingServerEntriesAndNullInputs(): void {
		$previousExists = array_key_exists('_SERVER', $GLOBALS);
		$previous = $GLOBALS['_SERVER'] ?? null;
		$method = new ReflectionMethod(Base3IliasRuntime::class, 'prepareCliRequest');

		try {
			$GLOBALS['_SERVER'] = null;
			$method->invoke(null);
			if (PHP_SAPI === 'cli') {
				self::assertSame('localhost', $GLOBALS['_SERVER']['HTTP_HOST']);
				self::assertSame('/', $GLOBALS['_SERVER']['REQUEST_URI']);
				self::assertSame('off', $GLOBALS['_SERVER']['HTTPS']);
			}

			$GLOBALS['_SERVER'] = ['HTTP_HOST' => 'example.test', 'HTTPS' => 'on', 'ORIGINAL' => 123];
			$method->invoke(null);
			self::assertSame('example.test', $GLOBALS['_SERVER']['HTTP_HOST']);
			self::assertSame('on', $GLOBALS['_SERVER']['HTTPS']);
			self::assertSame(123, $GLOBALS['_SERVER']['ORIGINAL']);
		} finally {
			if ($previousExists) $GLOBALS['_SERVER'] = $previous;
			else unset($GLOBALS['_SERVER']);
		}
	}
}
