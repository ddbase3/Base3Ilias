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

use Base3\CoreFoundation\Api\ISystemService;
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
		$framework = $this->moduleRegistry->getModule('Core');
		if ($framework === null) return '';

		$version = $framework['version'] ?? null;
		return is_string($version) ? trim($version) : '';
	}
}
