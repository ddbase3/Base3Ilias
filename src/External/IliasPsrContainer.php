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


namespace Base3\Base3Ilias\External;

use Psr\Container\ContainerInterface;
use ILIAS\DI\Container as IliasContainer;

class IliasPsrContainer implements ContainerInterface {

	protected IliasContainer $ilias;

	public function __construct(IliasContainer $ilias) {
		$this->ilias = $ilias;
	}

	public function get($id): mixed {
		if (!$this->has($id)) {
			throw new \RuntimeException("Service '$id' not found in ILIAS container");
		}

		return $this->ilias[$id];
	}

	public function has($id): bool {
		return isset($this->ilias[$id]);
	}

	public function keys(): array {
		return $this->ilias->keys();
	}
}

