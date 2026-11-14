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


namespace Base3;

use Base3\Base3Ilias\Ilias\ComponentBootstrap;
use ILIAS\Component\Component;

require_once __DIR__ . '/src/Ilias/ComponentBootstrap.php';

/**
 * Native BASE3-vendor ILIAS component wrapper.
 *
 * The reusable integration lives in the Base3Ilias BASE3 module. This wrapper
 * only provides the ILIAS component identity for installations that expose the
 * module directly as components/Base3/Base3Ilias.
 */
class Base3Ilias implements Component {

	private ?ComponentBootstrap $bootstrap = null;

	public function init(
		array | \ArrayAccess &$define,
		array | \ArrayAccess &$implement,
		array | \ArrayAccess &$use,
		array | \ArrayAccess &$contribute,
		array | \ArrayAccess &$seek,
		array | \ArrayAccess &$provide,
		array | \ArrayAccess &$pull,
		array | \ArrayAccess &$internal,
	): void {
		$this->bootstrap ??= new ComponentBootstrap(__DIR__);
		$this->bootstrap->init(
			$this,
			$define,
			$implement,
			$use,
			$contribute,
			$seek,
			$provide,
			$pull,
			$internal
		);
	}
}
