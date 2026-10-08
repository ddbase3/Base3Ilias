<?php declare(strict_types=1);

namespace Base3;

use Base3Ilias\Ilias\ComponentBootstrap;
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
