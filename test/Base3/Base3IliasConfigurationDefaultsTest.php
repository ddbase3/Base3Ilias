<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasConfiguration;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class Base3IliasConfigurationDefaultsTest extends TestCase {

	private bool $hadServer = false;
	private mixed $originalServer = null;

	protected function setUp(): void {
		parent::setUp();
		$this->hadServer = array_key_exists('_SERVER', $GLOBALS);
		$this->originalServer = $GLOBALS['_SERVER'] ?? null;
		$GLOBALS['_SERVER'] = [];
	}

	protected function tearDown(): void {
		if ($this->hadServer) {
			$GLOBALS['_SERVER'] = $this->originalServer;
		} else {
			unset($GLOBALS['_SERVER']);
		}
		parent::tearDown();
	}

	public function testDefaultsFillMissingEntriesWithoutReplacingStoredValues(): void {
		$stored = [
			'base' => ['url' => null, 'intern' => 'custom'],
			'other' => ['setting' => false]
		];
		$defaults = ['base' => ['url' => '/default/', 'intern' => '', 'endpoint' => 'dispatch']];
		self::assertSame([
			'base' => ['url' => null, 'intern' => 'custom', 'endpoint' => 'dispatch'],
			'other' => ['setting' => false]
		], $this->invoke('mergeDefaults', $stored, $defaults));
	}

	public function testMissingOrMalformedGroupsAreInitializedFromDefaults(): void {
		$defaults = ['base' => ['endpoint' => 'dispatch', 'url' => '/']];
		self::assertSame($defaults, $this->invoke('mergeDefaults', [], $defaults));
		self::assertSame($defaults, $this->invoke('mergeDefaults', ['base' => 'invalid'], $defaults));
	}

	public function testHttpsSchemeUsesForwardedProtoOrNativeHttpsFlag(): void {
		$GLOBALS['_SERVER'] = ['HTTP_X_FORWARDED_PROTO' => 'HTTPS'];
		self::assertSame('https', $this->invoke('resolveScheme'));
		$GLOBALS['_SERVER'] = ['HTTPS' => 'on'];
		self::assertSame('https', $this->invoke('resolveScheme'));
		$GLOBALS['_SERVER'] = ['HTTPS' => '1'];
		self::assertSame('https', $this->invoke('resolveScheme'));
	}

	public function testHttpSchemeIsDefaultWhenNoHttpsIndicatorIsSet(): void {
		self::assertSame('http', $this->invoke('resolveScheme'));
		$GLOBALS['_SERVER'] = ['HTTP_X_FORWARDED_PROTO' => 'http', 'HTTPS' => 'off'];
		self::assertSame('http', $this->invoke('resolveScheme'));
	}

	private function invoke(string $method, mixed ...$args): mixed {
		$instance = (new ReflectionClass(Base3IliasConfiguration::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(Base3IliasConfiguration::class, $method))->invoke($instance, ...$args);
	}
}
