<?php declare(strict_types=1);

namespace Base3;

use ILIAS\Component\Component;
use ILIAS\Component\Resource\PublicAsset;
use ILIAS\Component\Resource\Endpoint;
use Base3\Base3Ilias\Base3IliasPublicAsset;

class Base3Ilias implements Component {

	private $verbose = true;

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

	private function out($str = '') {
		if (!$this->verbose) return;
		echo $str . "\n";
	}
}

