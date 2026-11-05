<?php declare(strict_types=1);

namespace Base3Ilias\Test\Ilias;

use Base3Ilias\Ilias\ComponentBootstrap;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

final class ComponentBootstrapFilesystemTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		parent::setUp();
		$this->root = sys_get_temp_dir() . '/base3_component_bootstrap_' . bin2hex(random_bytes(8));
		self::assertTrue(mkdir($this->root . '/components/Base3/Base3Ilias', 0770, true));
		$this->writeIni();
	}

	protected function tearDown(): void {
		try {
			$this->removeFixture($this->root);
		}
		finally {
			parent::tearDown();
		}
	}

	public function testResolvesIliasRootByAscendingFromNestedComponent(): void {
		$bootstrap = new ComponentBootstrap($this->root . '/components/Base3/Base3Ilias');
		self::assertSame($this->root, $this->invoke($bootstrap, 'resolveIliasRoot'));
	}

	public function testCannotResolveMissingComponentDirectory(): void {
		$bootstrap = new ComponentBootstrap($this->root . '/missing');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('ILIAS component root could not be resolved:');
		$this->invoke($bootstrap, 'resolveIliasRoot');
	}

	public function testRejectsExistingComponentPathWithoutIliasRootMarker(): void {
		self::assertTrue(unlink($this->root . '/ilias.ini.php'));
		$bootstrap = new ComponentBootstrap($this->root . '/components/Base3/Base3Ilias');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('ILIAS root directory could not be resolved.');
		$this->invoke($bootstrap, 'resolveIliasRoot');
	}

	public function testResolvesPerClientArtifactPathFromConfiguration(): void {
		$bootstrap = $this->bootstrap();
		$expected = $this->root . '/data/client/base3/artifacts';
		self::assertSame($expected, $this->invoke($bootstrap, 'resolveArtifactsDirectory', $this->root));
	}

	public function testAbsentOrIncompleteConfigurationHasNoArtifactsDirectory(): void {
		$bootstrap = $this->bootstrap();
		unlink($this->root . '/ilias.ini.php');
		self::assertNull($this->invoke($bootstrap, 'resolveArtifactsDirectory', $this->root));

		file_put_contents($this->root . '/ilias.ini.php', "[clients]\n default = client\n");
		self::assertNull($this->invoke($bootstrap, 'resolveArtifactsDirectory', $this->root));

		file_put_contents($this->root . '/ilias.ini.php', "[clients]\n datadir = \"\"\n default = client\n");
		self::assertNull($this->invoke($bootstrap, 'resolveArtifactsDirectory', $this->root));

		file_put_contents($this->root . '/ilias.ini.php', '[clients]' . "\n datadir = " . json_encode($this->root . '/data', JSON_UNESCAPED_SLASHES) . "\n default = \"\"\n");
		self::assertNull($this->invoke($bootstrap, 'resolveArtifactsDirectory', $this->root));
	}

	public function testComponentRegistryIsCachedForOneBootstrapInstance(): void {
		$bootstrap = $this->bootstrap();
		$one = $this->invoke($bootstrap, 'getModuleRegistry');
		$two = $this->invoke($bootstrap, 'getModuleRegistry');
		self::assertSame($one, $two);
		self::assertSame([], $one->getModules());
	}

	public function testCliInitializationClearsOnlyOwnClientArtifacts(): void {
		$bootstrap = $this->bootstrap();
		$artifacts = $this->root . '/data/client/base3/artifacts';
		self::assertTrue(mkdir($artifacts . '/nested', 0770, true));
		file_put_contents($artifacts . '/base3modules.php', 'stale');
		file_put_contents($artifacts . '/nested/deep.log', 'stale');
		$neighbor = $this->root . '/data/client/keep.txt';
		file_put_contents($neighbor, 'keep');
		$flag = new ReflectionProperty(ComponentBootstrap::class, 'artifactsCleaned');
		$original = $flag->getValue();
		try {
			$flag->setValue(null, false);
			$this->invoke($bootstrap, 'clearArtifactsOnCliInit');
			self::assertDirectoryExists($artifacts);
			self::assertSame(['.', '..'], scandir($artifacts));
			self::assertSame('keep', file_get_contents($neighbor));
			file_put_contents($artifacts . '/after-first-call', 'later');
			$this->invoke($bootstrap, 'clearArtifactsOnCliInit');
			self::assertFileExists($artifacts . '/after-first-call');
		}
		finally {
			$flag->setValue(null, $original);
		}
	}

	public function testArtifactCleanupDoesNotFollowSymlinksOutsideTheArtifactRoot(): void {
		$artifacts = $this->root . '/data/client/base3/artifacts';
		self::assertTrue(mkdir($artifacts, 0770, true));
		$outside = $this->root . '/keep.txt';
		file_put_contents($outside, 'outside');
		self::assertTrue(symlink($outside, $artifacts . '/link'));
		$this->invoke($this->bootstrap(), 'deleteDirectoryContents', $artifacts);
		self::assertFileDoesNotExist($artifacts . '/link');
		self::assertSame('outside', file_get_contents($outside));
	}

	public function testNestedArtifactDeletionRemovesContentsWithoutRemovingRoot(): void {
		$artifacts = $this->root . '/data/client/base3/artifacts';
		self::assertTrue(mkdir($artifacts . '/one/two', 0770, true));
		file_put_contents($artifacts . '/one/two/file.bin', 'data');
		file_put_contents($artifacts . '/one/another.txt', 'data');
		$this->invoke($this->bootstrap(), 'deleteDirectoryContents', $artifacts);
		self::assertDirectoryExists($artifacts);
		self::assertSame(['.', '..'], scandir($artifacts));
	}

	private function bootstrap(): ComponentBootstrap {
		return new ComponentBootstrap($this->root . '/components/Base3/Base3Ilias');
	}

	private function invoke(ComponentBootstrap $bootstrap, string $method, mixed ...$arguments): mixed {
		return (new ReflectionMethod($bootstrap, $method))->invoke($bootstrap, ...$arguments);
	}

	private function writeIni(): void {
		file_put_contents($this->root . '/ilias.ini.php',
			'[clients]' . "\n"
			. 'datadir = ' . json_encode($this->root . '/data', JSON_UNESCAPED_SLASHES) . "\n"
			. 'default = client' . "\n"
		);
	}

	private function removeFixture(string $path): void {
		if (!is_dir($path)) return;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($iterator as $entry) {
			if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname());
			else unlink($entry->getPathname());
		}
		rmdir($path);
	}
}
