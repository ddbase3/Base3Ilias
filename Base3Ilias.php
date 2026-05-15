<?php declare(strict_types=1);

namespace Base3;

use ILIAS\Component\Component;
use ILIAS\Component\Resource\PublicAsset;
use ILIAS\Component\Resource\Endpoint;
use Base3\Base3Ilias\Base3IliasPublicAsset;
use RuntimeException;

class Base3Ilias implements Component {

	private $verbose = false;

	private static bool $artifactsCleaned = false;

	public function init(
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

		// deprecated
		$endpoint = 'base3.php';
		$this->out('Deploy endpoint: ' . $endpoint);
		$contribute[PublicAsset::class] = fn() => new Endpoint($this, $endpoint);

		$this->out();

		$this->deployAssets($contribute);

		$this->out();
		$this->out('-----------------------------------------------------------');
		$this->out();
	}

	/**
	 * Deploy all Base3 plugin assets to public/components/Base3/[PluginName]
	 * Later use Base3IliasAssetResolver for getting target URLs.
	 */
	private function deployAssets(array | \ArrayAccess $contribute) {
		$pluginBase = dirname(__DIR__, 1);
		$this->out('Plugin base dir: ' . $pluginBase);

		foreach (glob($pluginBase . '/*', GLOB_ONLYDIR) as $pluginPath) {
			$pluginName = basename($pluginPath);
			$this->out('* Plugin: ' . $pluginName);

			$assetsPath = $pluginPath . "/assets";
			if (!is_dir($assetsPath)) {
				$this->out('  - no assets');
				continue;
			}

			$source = "components/Base3/" . $pluginName . "/assets";
			$target = "components/Base3/" . $pluginName;
			$this->out('  - deploy ' . $source . ' => ' . $target);
			$asset = new Base3IliasPublicAsset($source, $target);
			$contribute[PublicAsset::class] = static fn() => $asset;
		}
	}

	private function clearArtifactsOnCliInit(): void {
		if (self::$artifactsCleaned) return;
		if (PHP_SAPI !== 'cli') return;

		self::$artifactsCleaned = true;

		$artifactsDir = $this->resolveArtifactsDirectory();
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

	private function resolveArtifactsDirectory(): ?string {
		$iliasDir = realpath(dirname(__DIR__, 3));
		if ($iliasDir === false) return null;

		$configFile = $iliasDir . DIRECTORY_SEPARATOR . 'ilias.ini.php';
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

	private function out($str = '') {
		if (!$this->verbose) return;
		echo $str . "\n";
	}
}
