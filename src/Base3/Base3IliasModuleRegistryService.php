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

use Base3\CoreFoundation\Api\IModuleRegistry;
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
