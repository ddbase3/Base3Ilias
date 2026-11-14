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


namespace Base3\Base3Ilias\Ilias;

use Base3\CoreFoundation\Api\ISystemService;
use ILIAS\DI\Container as IliasContainer;
use ILIAS\UI\Component\Component;
use RuntimeException;

/** Adapts the ILIAS 9/10 and 11/12 native legacy-content factory signatures. */
final class Base3IliasLegacyContentAdapter {

	public static function create(ISystemService $system, IliasContainer $ilias, string $html): Component {
		$version = trim($system->getHostSystemVersion());
		if ($system->getHostSystemName() !== 'ILIAS' || $version === '') {
			throw new RuntimeException('An ILIAS host version is required for native legacy content.');
		}

		$ui = $ilias->ui()->factory();
		if (version_compare($version, '11.0', '<')) {
			return $ui->legacy($html);
		}

		return $ui->legacy()->content($html);
	}
}
