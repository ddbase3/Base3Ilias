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

use Base3\Base3Ilias\Base3IliasModuleRegistry;
use Base3\Core\MvcView;
use Base3\CoreFoundation\Language\Api\ILanguage;

final class Base3IliasMvcView extends MvcView {

	public function __construct(
		ILanguage $language,
		private readonly Base3IliasModuleRegistry $moduleRegistry
	) {
		parent::__construct($language);
	}

	public function setPath(string $path = '.') {
		parent::setPath($this->resolveModulePath($path));
	}

	private function resolveModulePath(string $path): string {
		$normalizedPath = $this->normalizePath($path);

		foreach ($this->getLegacyRoots() as $legacyRoot) {
			$normalizedRoot = $this->normalizePath($legacyRoot);
			$prefix = $normalizedRoot . DIRECTORY_SEPARATOR;

			if (!str_starts_with($normalizedPath . DIRECTORY_SEPARATOR, $prefix)) continue;

			$relativePath = trim(substr($normalizedPath, strlen($prefix)), DIRECTORY_SEPARATOR);
			if ($relativePath === '' || str_contains($relativePath, DIRECTORY_SEPARATOR)) continue;

			$modulePath = $this->moduleRegistry->getModulePath($relativePath);
			if ($modulePath !== null) {
				return $modulePath;
			}
		}

		return $path;
	}

	private function getLegacyRoots(): array {
		$roots = [];

		if (defined('DIR_PLUGIN')) $roots[] = DIR_PLUGIN;
		if (defined('DIR_BASE3')) $roots[] = DIR_BASE3;

		return array_values(array_unique($roots));
	}

	private function normalizePath(string $path): string {
		return rtrim(
			str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path),
			DIRECTORY_SEPARATOR
		);
	}
}
