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


namespace Base3\Base3Ilias\Api;

use Base3\CoreFoundation\Api\IBase;
use ILIAS\GlobalScreen\Identification\IdentificationInterface;
use ILIAS\GlobalScreen\Scope\MetaBar\Factory\isItem;
use ILIAS\GlobalScreen\Scope\MetaBar\Factory\MetaBarItemFactory;

/**
 * Discoverable BASE3 extension point for ILIAS Meta Bar items.
 *
 * Implementations own the complete item including symbol, title, position,
 * visibility, content, and behavior. The ILIAS adapter only aggregates all
 * implementations discovered through IClassMap.
 */
interface IMetaBarItemProvider extends IBase {

	public function getMetaBarItem(
		MetaBarItemFactory $metaBar,
		IdentificationInterface $identification
	): isItem;
}
