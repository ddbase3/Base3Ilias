<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Api\ISystemService;
use Base3Ilias\Base3\Base3IliasAssetResolver;
use Base3Ilias\Test\Fixture\Base3IliasRegistryFixture;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Fixture/Base3IliasRegistryFixture.php';

final class Base3IliasAssetResolverTest extends TestCase {

	private Base3IliasRegistryFixture $fixture;

	protected function setUp(): void {
		parent::setUp();
		$this->fixture = new Base3IliasRegistryFixture();
		$this->fixture->addModule('Demo');
	}

	protected function tearDown(): void {
		$this->fixture->remove();
		parent::tearDown();
	}

	public function testUnknownAndMalformedPathsRemainUnchanged(): void {
		$resolver = $this->resolver('11.0');
		foreach (['', '/assets/app.js', 'plugin/Demo/file.js', 'plugin/Demo/assets', 'plugin/Unknown/assets/app.js'] as $path) {
			self::assertSame($path, $resolver->resolve($path));
		}
	}

	public function testMissingFileUsesZeroHashWithModernIliasPublicPath(): void {
		self::assertSame(
			'./components/Base3/Demo/js/app.js?t=000000',
			$this->resolver('11.0')->resolve('plugin/Demo/assets/js/app.js')
		);
	}

	public function testExistingAssetUsesFileContentHash(): void {
		$file = $this->fixture->write('Demo', 'assets/js/app.js', 'example');
		self::assertSame(
			'./components/Base3/Demo/js/app.js?t=' . substr(md5_file($file), 0, 6),
			$this->resolver('12.0')->resolve('plugin/Demo/assets/js/app.js')
		);
	}

	public function testIliasNineUsesOriginalModulePath(): void {
		self::assertSame(
			'./Demo/assets/css/site.css?t=000000',
			$this->resolver('9.9')->resolve('plugin/Demo/assets/css/site.css')
		);
	}

	public function testUnknownVersionDoesNotGuessLegacyAssetLocation(): void {
		self::assertSame(
			'./components/Base3/Demo/image.svg?t=000000',
			$this->resolver(' ')->resolve('plugin/Demo/assets/image.svg')
		);
	}

	private function resolver(string $version): Base3IliasAssetResolver {
		$system = $this->createMock(ISystemService::class);
		$system->method('getHostSystemVersion')->willReturn($version);
		return new Base3IliasAssetResolver($system, $this->fixture->registry());
	}
}
