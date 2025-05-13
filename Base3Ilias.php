<?php declare(strict_types=1);

namespace Base3;

use ILIAS\Component\Component;
use ILIAS\Component\Resource\PublicAsset;
use ILIAS\Component\Resource\Endpoint;

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

		$contribute[PublicAsset::class] = fn() => new Endpoint($this, 'base3.php');
	
		$this->deployAssets();
	}

	/**
	 * Deploy all Base3 plugin assets to public/components/Base3/[PluginName]
	 * Use Base3IliasAssetResolver for getting target URLs.
	 */
	private function deployAssets() {
		$pluginBase = dirname(__DIR__, 1);

		$this->out();
		$this->out('-- BASE3 ILIAS Integration --------------------------------');

		$this->out('Plugin base dir: ' . $pluginBase);
		foreach (glob($pluginBase . '/*', GLOB_ONLYDIR) as $pluginPath) {
			$pluginName = basename($pluginPath);
			$this->out('* Plugin: ' . $pluginName);

			$assetsPath = $pluginPath . "/assets";
			if (!is_dir($assetsPath)) {
				$this->out('  - no assets');
				continue;
			}

			$this->out('  - deploy assets');
			$source = "components/Base3/" . $pluginName . "/assets";
			$target = "components/Base3/" . $pluginName;
			$asset = new Base3IliasPublicAsset($source, $target);
			$contribute[PublicAsset::class] = static fn() => $asset;
		}

		$this->out('-----------------------------------------------------------');
		$this->out();
	}

	private function out($str = '') {
		if (!$this->verbose) return;
		echo $str . "\n";
	}
}

