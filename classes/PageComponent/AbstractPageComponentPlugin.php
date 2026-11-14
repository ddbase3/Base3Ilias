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


namespace Base3\Base3Ilias\PageComponent;

use ilPageComponentPlugin;

abstract class AbstractPageComponentPlugin extends ilPageComponentPlugin {

	public function getPluginName(): string {
		return preg_replace('/^il|Plugin$/', '', static::class);
	}

	public function isValidParentType(string $a_type): bool {
		return true;
	}
}
