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


namespace Base3\Base3Ilias\Migration;

use Base3\CoreFoundation\Migration\Api\IDatabaseMigrationProvider;
use Base3\Base3Ilias\Service\Base3IliasGroupConfiguration;

/** Owns versioned conversion of Base3Ilias group configuration stored in the active ILIAS settings backend. */
final class Base3IliasGroupCriteriaMigrationProvider implements IDatabaseMigrationProvider {

	public function __construct(private readonly Base3IliasGroupConfiguration $configuration) {}

	public static function getName(): string { return 'base3iliasgroupcriteriamigrationprovider'; }

	public function isActive(): bool { return true; }

	public function getMigrations(): array {
		return [new Migration001ConvertGroupAssignments($this->configuration)];
	}
}
