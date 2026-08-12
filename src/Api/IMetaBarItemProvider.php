<?php declare(strict_types=1);

namespace Base3Ilias\Api;

use Base3\Api\IBase;
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
