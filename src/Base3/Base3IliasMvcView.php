<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Base3Ilias\Base3IliasModuleRegistry;
use Base3\Core\MvcView;
use Base3\Language\Api\ILanguage;

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
