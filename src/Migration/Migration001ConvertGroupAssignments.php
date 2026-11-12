<?php declare(strict_types=1);

namespace Base3Ilias\Migration;

use Base3\Migration\Api\IDatabaseMigration;
use Base3\Migration\Api\ILegacyDatabaseMigration;
use Base3\Migration\Exception\MigrationException;
use Base3Ilias\Service\Base3IliasGroupConfiguration;

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
