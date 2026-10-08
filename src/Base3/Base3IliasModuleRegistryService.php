<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\IModuleRegistry;
use Base3\Base3Ilias\Base3IliasModuleRegistry;

final class Base3IliasModuleRegistryService implements IModuleRegistry {

	public function __construct(
		private readonly Base3IliasModuleRegistry $moduleRegistry
	) {}

	public function getModuleNames(): array {
		return array_keys($this->moduleRegistry->getModules());
	}

	public function getModulePath(string $name): ?string {
		return $this->moduleRegistry->getModulePath($name);
	}

	public function requireModulePath(string $name): string {
		return $this->moduleRegistry->requireModulePath($name);
	}
}
