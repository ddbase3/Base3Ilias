<?php declare(strict_types=1);

namespace Base3Ilias\Test\Ilias;

use Base3\Base3Ilias\Base3IliasActivityRepositoryBridge;
use Base3\Base3Ilias\Base3IliasPublicAsset;
use Base3Ilias\Ilias\ComponentBootstrap;
use ILIAS\Component\Component;
use ILIAS\Component\Resource\PublicAsset;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ComponentBootstrapInitialisationTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		parent::setUp();
		$this->root = sys_get_temp_dir() . '/base3_component_init_' . bin2hex(random_bytes(8));
		self::assertTrue(mkdir($this->root . '/components/Base3', 0770, true));
		file_put_contents($this->root . '/ilias.ini.php', "[clients]\ndefault = client\n");
	}

	protected function tearDown(): void {
		try {
			$this->removeFixture($this->root);
		}
		finally {
			parent::tearDown();
		}
	}

	public function testInitialisesEndpointContributionWithoutResolvingNativeDependencies(): void {
		$component = $this->createMock(Component::class);
		$contribute = [];
		$internal = [];
		$this->initialize($component, $contribute, $internal);
		self::assertArrayHasKey(PublicAsset::class, $contribute);
		self::assertInstanceOf(\Closure::class, $contribute[PublicAsset::class]);
		if (interface_exists(\ILIAS\Component\Activities\Repository::class)) {
			self::assertArrayHasKey(Base3IliasActivityRepositoryBridge::class, $internal);
			self::assertInstanceOf(\Closure::class, $internal[Base3IliasActivityRepositoryBridge::class]);
		}
		else {
			self::assertSame([], $internal);
		}
	}

	public function testInitialisesPublicAssetContributionFromDiscoveredModule(): void {
		$path = $this->root . '/components/Base3/Example';
		self::assertTrue(mkdir($path . '/assets', 0770, true));
		file_put_contents($path . '/base3.json', json_encode([
			'manifestVersion' => 1, 'name' => 'Example', 'namespace' => 'Example', 'version' => '1.0.0'
		], JSON_THROW_ON_ERROR));
		$contribute = [];
		$internal = [];
		$this->initialize($this->createMock(Component::class), $contribute, $internal);
		$asset = $contribute[PublicAsset::class]();
		self::assertInstanceOf(Base3IliasPublicAsset::class, $asset);
		self::assertSame('components/Base3/Example/assets', $asset->getSource());
		self::assertSame('components/Base3/Example', $asset->getTarget());
	}

	private function initialize(Component $component, array &$contribute, array &$internal): void {
		$bootstrap = new ComponentBootstrap($this->root . '/components/Base3');
		$flag = new ReflectionProperty(ComponentBootstrap::class, 'artifactsCleaned');
		$original = $flag->getValue();
		try {
			$flag->setValue(null, false);
			$define = $implement = $use = $seek = $provide = $pull = [];
			$bootstrap->init($component, $define, $implement, $use, $contribute, $seek, $provide, $pull, $internal);
		}
		finally {
			$flag->setValue(null, $original);
		}
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
