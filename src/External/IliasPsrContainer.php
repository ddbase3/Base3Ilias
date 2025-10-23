<?php declare(strict_types=1);

namespace Base3Ilias\External;

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

