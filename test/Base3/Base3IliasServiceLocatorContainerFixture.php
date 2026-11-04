<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\External\IliasPsrContainer;
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
