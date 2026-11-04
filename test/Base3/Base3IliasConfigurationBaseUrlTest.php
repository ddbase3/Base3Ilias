<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasConfiguration;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class Base3IliasConfigurationBaseUrlTest extends TestCase {

	private bool $hadServer = false;
	private mixed $originalServer = null;

	protected function setUp(): void {
		parent::setUp();
		$this->hadServer = array_key_exists('_SERVER', $GLOBALS);
		$this->originalServer = $GLOBALS['_SERVER'] ?? null;
		$GLOBALS['_SERVER'] = [];
	}

	protected function tearDown(): void {
		if ($this->hadServer) $GLOBALS['_SERVER'] = $this->originalServer;
		else unset($GLOBALS['_SERVER']);
		parent::tearDown();
	}

	public function testNativeIliasHttpPathIsAuthoritativeWhenConfigured(): void {
		if (defined('ILIAS_HTTP_PATH') && is_string(ILIAS_HTTP_PATH) && ILIAS_HTTP_PATH !== '') {
			self::assertSame(rtrim(ILIAS_HTTP_PATH, '/') . '/', $this->resolveBaseUrl());
			return;
		}
		self::assertSame('/', $this->resolveBaseUrl());
	}

	public function testHostUrlIncludesScriptDirectoryWhenNoNativeHttpPathIsDefined(): void {
		$GLOBALS['_SERVER'] = ['HTTP_HOST' => 'example.test:8443', 'HTTPS' => 'on', 'SCRIPT_NAME' => '/ilias/index.php'];
		$actual = $this->resolveBaseUrl();
		if (defined('ILIAS_HTTP_PATH') && is_string(ILIAS_HTTP_PATH) && ILIAS_HTTP_PATH !== '') {
			self::assertSame(rtrim(ILIAS_HTTP_PATH, '/') . '/', $actual);
		} else {
			self::assertSame('https://example.test:8443/ilias/', $actual);
		}
	}

	public function testRootScriptProducesRootUrlWithoutDuplicateSeparators(): void {
		$GLOBALS['_SERVER'] = ['HTTP_HOST' => 'example.test', 'SCRIPT_NAME' => '/index.php'];
		$actual = $this->resolveBaseUrl();
		if (defined('ILIAS_HTTP_PATH') && is_string(ILIAS_HTTP_PATH) && ILIAS_HTTP_PATH !== '') {
			self::assertSame(rtrim(ILIAS_HTTP_PATH, '/') . '/', $actual);
		} else {
			self::assertSame('http://example.test/', $actual);
		}
	}

	private function resolveBaseUrl(): string {
		$adapter = (new ReflectionClass(Base3IliasConfiguration::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(Base3IliasConfiguration::class, 'resolveBaseUrl'))->invoke($adapter);
	}
}
