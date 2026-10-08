<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\ISystemService;
use Base3\Base3Ilias\Base3IliasModuleRegistry;

final class Base3IliasSystemService implements ISystemService {

	public function __construct(
		private readonly Base3IliasModuleRegistry $moduleRegistry
	) {}

	public function getHostSystemName() : string {
		return 'ILIAS';
	}

	public function getHostSystemVersion() : string {
		if (defined('ILIAS_VERSION_NUMERIC')) {
			return trim((string) ILIAS_VERSION_NUMERIC);
		}

		$versionFile = rtrim(DIR_ILIAS, '/\\') . DIRECTORY_SEPARATOR . 'ilias_version.php';
		if (!is_file($versionFile)) return '';

		require_once $versionFile;

		return defined('ILIAS_VERSION_NUMERIC')
			? trim((string) ILIAS_VERSION_NUMERIC)
			: '';
	}

	public function getEmbeddedSystemName() : string {
		return 'BASE3';
	}

	public function getEmbeddedSystemVersion() : string {
		$framework = $this->moduleRegistry->getModule('Base3Framework');
		if ($framework === null) return '';

		$version = $framework['version'] ?? null;
		return is_string($version) ? trim($version) : '';
	}
}
