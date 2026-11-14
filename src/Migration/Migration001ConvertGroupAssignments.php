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

use Base3\CoreFoundation\Migration\Api\IDatabaseMigration;
use Base3\CoreFoundation\Migration\Api\ILegacyDatabaseMigration;
use Base3\CoreFoundation\Migration\Exception\MigrationException;
use Base3\Base3Ilias\Service\Base3IliasGroupConfiguration;

/** Immutable data-format migration from old ILIAS group ID assignments to criterion expressions. */
final class Migration001ConvertGroupAssignments implements IDatabaseMigration, ILegacyDatabaseMigration {

	public function __construct(private readonly Base3IliasGroupConfiguration $configuration) {}

	public static function getName(): string { return 'migration001convertgroupassignments'; }

	public function getVersion(): string { return '20261019001'; }

	public function getDescription(): string { return 'Convert ILIAS BASE3 group assignments into canonical user criteria.'; }

	public function up(): void { $this->configuration->migrateLegacy(); }

	public function down(): void {
		throw new MigrationException('Not implemented');
	}
}
