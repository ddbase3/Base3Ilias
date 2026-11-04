<?php declare(strict_types=1);

namespace Base3Ilias\Test\Fixture;

use Base3\Base3Ilias\Base3IliasModuleRegistry;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/classes/Base3IliasModuleRegistry.php';

/** Filesystem fixture for the real BASE3 module registry and its host adapters. */
final class Base3IliasRegistryFixture {

	public readonly string $root;

	public function __construct() {
		$this->root = sys_get_temp_dir() . '/base3ilias_registry_test_' . bin2hex(random_bytes(8));
		if (!mkdir($this->root, 0700, true)) {
			throw new RuntimeException('Could not create isolated module registry fixture.');
		}
	}

	public function addModule(string $name, string $version = '1.0.0'): string {
		$directory = $this->root . '/' . $name;
		$this->makeDirectory($directory);
		file_put_contents($directory . '/base3.json', json_encode([
			'manifestVersion' => 1,
			'name' => $name,
			'namespace' => $name,
			'version' => $version
		], JSON_THROW_ON_ERROR));
		return $directory;
	}

	public function write(string $module, string $relativePath, string $content): string {
		$file = $this->root . '/' . $module . '/' . $relativePath;
		$this->makeDirectory(dirname($file));
		file_put_contents($file, $content);
		return $file;
	}

	public function registry(): Base3IliasModuleRegistry {
		return new Base3IliasModuleRegistry($this->root, [$this->root]);
	}

	public function remove(): void {
		if (!is_dir($this->root)) return;
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($items as $item) {
			if ($item->isDir()) rmdir($item->getPathname());
			else unlink($item->getPathname());
		}
		rmdir($this->root);
	}

	private function makeDirectory(string $directory): void {
		if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
			throw new RuntimeException('Could not create module fixture directory.');
		}
	}
}
