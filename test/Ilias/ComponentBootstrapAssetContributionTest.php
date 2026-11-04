<?php declare(strict_types=1);

namespace Base3Ilias\Test\Ilias;

use Base3\Base3Ilias\Base3IliasModuleRegistry;
use Base3\Base3Ilias\Base3IliasPublicAsset;
use Base3Ilias\Ilias\ComponentBootstrap;
use ILIAS\Component\Resource\PublicAsset;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

final class ComponentBootstrapAssetContributionTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		parent::setUp();
		$this->root = sys_get_temp_dir() . '/base3_public_assets_' . bin2hex(random_bytes(8));
		self::assertTrue(mkdir($this->root . '/components/Base3', 0770, true));
	}

	protected function tearDown(): void {
		try {
			$this->removeFixture($this->root);
		}
		finally {
			parent::tearDown();
		}
	}

	public function testComponentWithNoAssetsContributesNoPublicAsset(): void {
		$this->writeModule('NoAssets', false);
		$contribute = [];
		$this->deploy($contribute);
		self::assertSame([], $contribute);
	}

	public function testAssetContributionUsesTheDiscoveredModuleSourceAndTarget(): void {
		$this->writeModule('Example', true);
		$contribute = [];
		$this->deploy($contribute);
		self::assertArrayHasKey(PublicAsset::class, $contribute);
		self::assertCount(1, $contribute);
		self::assertInstanceOf(\Closure::class, $contribute[PublicAsset::class]);
		$asset = $contribute[PublicAsset::class]();
		self::assertInstanceOf(Base3IliasPublicAsset::class, $asset);
		self::assertSame('components/Base3/Example/assets', $asset->getSource());
		self::assertSame('components/Base3/Example', $asset->getTarget());
	}

	public function testContributedAssetsDoNotDependOnAnAssetFileBeingPresent(): void {
		$this->writeModule('EmptyAssets', true);
		$contribute = [];
		$this->deploy($contribute);
		self::assertInstanceOf(Base3IliasPublicAsset::class, $contribute[PublicAsset::class]());
	}

	private function deploy(array &$contribute): void {
		$registry = new Base3IliasModuleRegistry($this->root, [$this->root . '/components']);
		$bootstrap = new ComponentBootstrap($this->root . '/components/Base3');
		(new ReflectionProperty(ComponentBootstrap::class, 'moduleRegistry'))->setValue($bootstrap, $registry);
		$method = new ReflectionMethod(ComponentBootstrap::class, 'deployAssets');
		$arguments = [&$contribute];
		$method->invokeArgs($bootstrap, $arguments);
	}

	private function writeModule(string $name, bool $withAssets): void {
		$path = $this->root . '/components/Base3/' . $name;
		self::assertTrue(mkdir($path, 0770, true));
		file_put_contents($path . '/base3.json', json_encode([
			'manifestVersion' => 1, 'name' => $name,
			'namespace' => $name, 'version' => '1.0.0'
		], JSON_THROW_ON_ERROR));
		if ($withAssets) self::assertTrue(mkdir($path . '/assets', 0770, true));
	}

	private function removeFixture(string $directory): void {
		if (!is_dir($directory)) return;
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($iterator as $entry) {
			if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname());
			else unlink($entry->getPathname());
		}
		rmdir($directory);
	}
}
