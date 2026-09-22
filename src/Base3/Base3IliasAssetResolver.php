<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\IAssetResolver;
use Base3\Api\ISystemService;

class Base3IliasAssetResolver implements IAssetResolver {

	public function __construct(
		private readonly ISystemService $systemService
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
		$subpath = array_slice($parts, 3);
		$target = $this->resolveTarget($plugin, $subpath);

		// Optionally add cache-busting query param
		unset($parts[0]);

		$realfile = DIR_PLUGIN . implode(DIRECTORY_SEPARATOR, $parts);
		$hash = file_exists($realfile) ? substr(md5_file($realfile), 0, 6) : '000000';

		return $target . '?t=' . $hash;
	}

	private function resolveTarget(string $plugin, array $subpath): string {
		if($this->usesDirectPluginAssets()) {
			$baseUrl = $this->getDirectBase3Url();
			if($baseUrl !== null) {
				return $baseUrl . '/' . $plugin . '/assets/' . implode('/', $subpath);
			}
		}

		return './components/Base3/' . $plugin . '/' . implode('/', $subpath);
	}

	private function usesDirectPluginAssets(): bool {
		$version = trim($this->systemService->getHostSystemVersion());

		return $version !== '' && version_compare($version, '10.0', '<');
	}

	private function getDirectBase3Url(): ?string {
		$iliasRoot = realpath(DIR_ILIAS);
		$base3Root = realpath(DIR_BASE3);

		if($iliasRoot === false || $base3Root === false) {
			return null;
		}

		$iliasRoot = rtrim(str_replace('\\', '/', $iliasRoot), '/');
		$base3Root = rtrim(str_replace('\\', '/', $base3Root), '/');
		$prefix = $iliasRoot . '/';

		if(!str_starts_with($base3Root . '/', $prefix)) {
			return null;
		}

		$relativePath = trim(substr($base3Root, strlen($prefix)), '/');
		if($relativePath === '') {
			return null;
		}

		return './' . $relativePath;
	}
}
