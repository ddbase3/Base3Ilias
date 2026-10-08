<?php declare(strict_types=1);

namespace Base3Ilias\Ilias;

use Base3\Base3Ilias\Base3IliasActivityRepositoryBridge;
use Base3\Base3Ilias\Base3IliasModuleRegistry;
use Base3\Base3Ilias\Base3IliasPublicAsset;
use ILIAS\Component\Component;
use ILIAS\Component\Resource\Endpoint;
use ILIAS\Component\Resource\PublicAsset;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/classes/Base3IliasActivityRepositoryBridge.php';
require_once dirname(__DIR__, 2) . '/classes/Base3IliasModuleRegistry.php';
require_once dirname(__DIR__, 2) . '/classes/Base3IliasPublicAsset.php';

final class ComponentBootstrap {

	private bool $verbose = false;

	private static bool $artifactsCleaned = false;

	private ?Base3IliasModuleRegistry $moduleRegistry = null;

	public function __construct(
		private readonly string $componentRoot
	) {}

	public function init(
		Component $component,
		array | \ArrayAccess &$define,
		array | \ArrayAccess &$implement,
		array | \ArrayAccess &$use,
		array | \ArrayAccess &$contribute,
		array | \ArrayAccess &$seek,
		array | \ArrayAccess &$provide,
		array | \ArrayAccess &$pull,
		array | \ArrayAccess &$internal,
	): void {
		$this->out();
		$this->out('-- BASE3 ILIAS Integration --------------------------------');
		$this->out();

		$this->clearArtifactsOnCliInit();

		if (interface_exists(\ILIAS\Component\Activities\Repository::class)) {
			$internal[Base3IliasActivityRepositoryBridge::class] = static fn() =>
				new Base3IliasActivityRepositoryBridge(
					$use[\ILIAS\Component\Activities\Repository::class]
				);

			// Reader probes must only record the dependency. At runtime publish a
			// lazy resolver, without resolving the repository during component init.
			if ($internal instanceof \Pimple\Container) {
				Base3IliasActivityRepositoryBridge::publishResolver(
					static fn() => $internal[Base3IliasActivityRepositoryBridge::class]
				);
			}
		}

		// deprecated
		$endpoint = 'base3.php';
		$this->out('Deploy endpoint: ' . $endpoint);
		$contribute[PublicAsset::class] = fn() => new Endpoint($component, $endpoint);

		$this->out();
		$this->deployAssets($contribute);
		$this->out();
		$this->out('-----------------------------------------------------------');
		$this->out();
	}

	/**
	 * Deploy all Base3 module assets to public/components/Base3/[ModuleName].
	 * Source modules may live at any depth below components/.
	 */
	private function deployAssets(array | \ArrayAccess $contribute): void {
		$registry = $this->getModuleRegistry();

		foreach ($registry->getModules() as $moduleName => $module) {
			$modulePath = $registry->resolveModulePath($module);
			$this->out('* Module: ' . $moduleName . ' (' . $module['path'] . ')');

			$assetsPath = $modulePath . DIRECTORY_SEPARATOR . 'assets';
			if (!is_dir($assetsPath)) {
				$this->out('  - no assets');
				continue;
			}

			$source = trim(str_replace('\\', '/', (string)$module['path']), '/') . '/assets';
			$target = 'components/Base3/' . $moduleName;
			$this->out('  - deploy ' . $source . ' => ' . $target);
			$asset = new Base3IliasPublicAsset($source, $target);
			$contribute[PublicAsset::class] = static fn() => $asset;
		}
	}

	private function getModuleRegistry(): Base3IliasModuleRegistry {
		if ($this->moduleRegistry !== null) {
			return $this->moduleRegistry;
		}

		$iliasRoot = $this->resolveIliasRoot();
		$componentsRoot = $iliasRoot . DIRECTORY_SEPARATOR . 'components';
		$artifactsDirectory = $this->resolveArtifactsDirectory($iliasRoot);
		$artifactFile = $artifactsDirectory === null
			? null
			: $artifactsDirectory . DIRECTORY_SEPARATOR . 'base3modules.php';

		$this->moduleRegistry = new Base3IliasModuleRegistry($iliasRoot, [$componentsRoot], $artifactFile);
		return $this->moduleRegistry;
	}

	private function clearArtifactsOnCliInit(): void {
		if (self::$artifactsCleaned) return;
		if (PHP_SAPI !== 'cli') return;

		self::$artifactsCleaned = true;

		$iliasRoot = $this->resolveIliasRoot();
		$artifactsDir = $this->resolveArtifactsDirectory($iliasRoot);
		if ($artifactsDir === null) {
			$this->out('Skip artifact cleanup: artifact directory could not be resolved');
			return;
		}

		if (!is_dir($artifactsDir)) {
			$this->out('Skip artifact cleanup: directory does not exist: ' . $artifactsDir);
			return;
		}

		$this->out('Clear artifacts: ' . $artifactsDir);
		$this->deleteDirectoryContents($artifactsDir);
	}

	private function resolveIliasRoot(): string {
		$path = realpath($this->componentRoot);
		if ($path === false) {
			throw new RuntimeException('ILIAS component root could not be resolved: ' . $this->componentRoot);
		}

		while (true) {
			if (is_file($path . DIRECTORY_SEPARATOR . 'ilias.ini.php')) {
				return $path;
			}

			$parent = dirname($path);
			if ($parent === $path) break;
			$path = $parent;
		}

		throw new RuntimeException('ILIAS root directory could not be resolved.');
	}

	private function resolveArtifactsDirectory(string $iliasRoot): ?string {
		$configFile = $iliasRoot . DIRECTORY_SEPARATOR . 'ilias.ini.php';
		if (!is_file($configFile)) return null;

		$parsed = parse_ini_file($configFile, true);
		if (!is_array($parsed)) return null;

		$dataDir = $parsed['clients']['datadir'] ?? null;
		$clientId = $parsed['clients']['default'] ?? null;

		if (!is_string($dataDir) || trim($dataDir) === '') return null;
		if (!is_string($clientId) || trim($clientId) === '') return null;

		return rtrim($dataDir, '/\\')
			. DIRECTORY_SEPARATOR . trim($clientId, '/\\')
			. DIRECTORY_SEPARATOR . 'base3'
			. DIRECTORY_SEPARATOR . 'artifacts';
	}

	private function deleteDirectoryContents(string $dir): void {
		$entries = scandir($dir);
		if ($entries === false) {
			throw new RuntimeException('Could not read artifact directory: ' . $dir);
		}

		foreach ($entries as $entry) {
			if ($entry === '.' || $entry === '..') continue;

			$path = $dir . DIRECTORY_SEPARATOR . $entry;
			$this->deletePath($path);
		}
	}

	private function deletePath(string $path): void {
		if (is_dir($path) && !is_link($path)) {
			$this->deleteDirectoryContents($path);

			if (!rmdir($path) && is_dir($path)) {
				throw new RuntimeException('Could not remove artifact directory: ' . $path);
			}

			return;
		}

		if (file_exists($path) && !unlink($path)) {
			throw new RuntimeException('Could not remove artifact file: ' . $path);
		}
	}

	private function out($str = ''): void {
		if (!$this->verbose) return;
		echo $str . "\n";
	}
}
