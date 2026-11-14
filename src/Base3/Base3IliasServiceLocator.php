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

use Psr\Container\ContainerInterface;
use ILIAS\DI\Container as IliasContainer;
use Base3\Core\ServiceLocator;

class Base3IliasServiceLocator extends ServiceLocator {

	protected ?ContainerInterface $iliasContainer = null;

	public function setIliasContainer(ContainerInterface $container): void {
		$this->iliasContainer = $container;
	}

        public function getServiceList(): array {
                $list = array_merge(parent::getServiceList(), $this->iliasContainer->keys());
                return $list;
        }

	public function get(string $name) {

		// 1. Zuerst normaler Zugriff
		$value = parent::get($name);
		if ($value !== null) return $value;

		// 2. Fallback auf ILIAS-Container
		if ($this->iliasContainer && $this->iliasContainer->has($name)) {
			return $this->iliasContainer->get($name);
		}

		return null;
	}

	public function has(string $name): bool {
		return parent::has($name)
			|| ($this->iliasContainer && $this->iliasContainer->has($name));
	}
}

