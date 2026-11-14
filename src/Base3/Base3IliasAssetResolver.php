<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Base3Ilias for BASE3 Framework.
 *
 * Base3Ilias integrates BASE3 with the ILIAS learning platform.
 * It provides native identity, services, assets and lifecycle adapters.
 * Application modules use the shared BASE3 Foundation contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0-or-later
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 ***********************************************************************/


namespace Base3\Base3Ilias\Base3;

use Base3\CoreFoundation\Api\IAssetResolver;
use Base3\CoreFoundation\Api\ISystemService;
use Base3\Base3Ilias\Base3IliasModuleRegistry;

class Base3IliasAssetResolver implements IAssetResolver {

	public function __construct(
		private readonly ISystemService $systemService,
		private readonly Base3IliasModuleRegistry $moduleRegistry
	) {}

	public function resolve(string $path): string {
		if(!str_starts_with($path, 'plugin/')) {
			return $path;
		}

		$parts = explode('/', $path);

		if(count($parts) < 4 || $parts[2] !== 'assets') {
			return $path;
		}

		$plugin = $parts[1];
		$module = $this->moduleRegistry->getModule($plugin);
		if($module === null) {
			return $path;
		}

		$subpath = array_slice($parts, 3);
		$target = $this->resolveTarget($plugin, $module, $subpath);

		$realfile = $this->moduleRegistry->resolveModulePath($module)
			. DIRECTORY_SEPARATOR . 'assets'
			. DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $subpath);
		$hash = file_exists($realfile) ? substr(md5_file($realfile), 0, 6) : '000000';

		return $target . '?t=' . $hash;
	}

	private function resolveTarget(string $plugin, array $module, array $subpath): string {
		if($this->usesDirectPluginAssets()) {
			$modulePath = trim(str_replace('\\', '/', (string)$module['path']), '/');
			return './' . $modulePath . '/assets/' . implode('/', $subpath);
		}

		return './components/Base3/' . $plugin . '/' . implode('/', $subpath);
	}

	private function usesDirectPluginAssets(): bool {
		$version = trim($this->systemService->getHostSystemVersion());

		return $version !== '' && version_compare($version, '10.0', '<');
	}
}
