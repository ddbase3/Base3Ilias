<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\ISystemService;

final class Base3IliasSystemService implements ISystemService {

	public function getHostSystemName() : string {
		return 'ILIAS';
	}

	public function getHostSystemVersion() : string {
		if (defined('ILIAS_VERSION_NUMERIC')) return ILIAS_VERSION_NUMERIC;

		$versionFile = DIR_ILIAS . '/ilias_version.php';
		if (!file_exists($versionFile)) return '';

		include $versionFile;
		return ILIAS_VERSION_NUMERIC;
	}

	public function getEmbeddedSystemName() : string {
		return 'BASE3';
	}

	public function getEmbeddedSystemVersion() : string {
		$versionFile = DIR_FRAMEWORK . '/VERSION';
		if (!file_exists($versionFile)) return '';

		$version = file_get_contents($versionFile);
		return trim($version);
	}
}
