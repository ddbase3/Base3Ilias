<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

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

