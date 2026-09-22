<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\ISystemService;

final class Base3IliasSystemService implements ISystemService {

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
		$versionFile = rtrim(DIR_FRAMEWORK, '/\\') . DIRECTORY_SEPARATOR . 'VERSION';
		if (!is_file($versionFile) || !is_readable($versionFile)) return '';

		$version = file_get_contents($versionFile);
		return is_string($version) ? trim($version) : '';
	}
}
