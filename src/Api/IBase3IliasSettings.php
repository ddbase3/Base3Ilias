<?php declare(strict_types=1);

namespace Base3Ilias\Api;

/**
 * Interface IBase3IliasSettings
 *
 * Provides configurable Base3Ilias settings.
 */
interface IBase3IliasSettings {

	/**
	 * Returns the administration display configuration.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getAdministrationConfig(): array;

}
