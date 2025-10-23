<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\IPlugin;
use Base3\Core\PluginClassMap;

class Base3IliasClassMap extends PluginClassMap {

	protected function getScanTargets(): array {
		$targets = parent::getScanTargets();

		$vendors = $this->getEntries(DIR_COMPONENTS);
		foreach ($vendors as $vendor) {

			// TODO check clean classes
			if ($vendor == 'Base3') continue;
			if ($vendor == 'ILIAS') continue;
			// if ($vendor == 'Qualitus') continue;

			$vendorPath = DIR_COMPONENTS . $vendor;
			if (!is_dir($vendorPath)) continue;

			$apps = $this->getEntries($vendorPath);
			foreach ($apps as $app) {
				$srcPath = $vendorPath . DIRECTORY_SEPARATOR . $app . DIRECTORY_SEPARATOR . 'src';
				if (!is_dir($srcPath)) continue;

				// andere Vendoren: App = Vendor/App, Namespace = Vendor\App
				$targets[] = [
					"basedir" => DIR_COMPONENTS,
					"app" => $vendor . '/' . $app,
					"subdir" => "src",
					"subns" => $vendor . "\\" . $app
				];
			}
		}
		
		return $targets;
	}
}

