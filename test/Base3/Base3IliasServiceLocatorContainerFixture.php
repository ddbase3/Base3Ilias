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


namespace Base3\Base3Ilias\Test\Base3;

use Base3\Base3Ilias\External\IliasPsrContainer;
use RuntimeException;

final class Base3IliasServiceLocatorContainerFixture extends IliasPsrContainer {

	public function __construct(private readonly array $services) {}

	public function get($id): mixed {
		if (!$this->has($id)) {
			throw new RuntimeException('Service not found: ' . (string)$id);
		}
		return $this->services[$id];
	}

	public function has($id): bool {
		return array_key_exists($id, $this->services);
	}

	public function keys(): array {
		return array_keys($this->services);
	}
}
