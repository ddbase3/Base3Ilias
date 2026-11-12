<?php declare(strict_types=1);

namespace Base3Ilias\Migration;

use Base3\Migration\Api\IDatabaseMigration;
use Base3\Migration\Api\ILegacyDatabaseMigration;
use Base3\Migration\Exception\MigrationException;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;

/** Immutable feature authorization data-format migration; never executed during access checks. */
final class Migration001ConvertFeatureGrants implements IDatabaseMigration, ILegacyDatabaseMigration {

	public function __construct(private readonly Base3IliasFeatureConfiguration $configuration) {}

	public static function getName(): string { return 'migration001convertfeaturegrants'; }

	public function getVersion(): string { return '20261019002'; }

	public function getDescription(): string { return 'Convert persisted BASE3 feature group grants into audience expressions.'; }

	public function up(): void { $this->configuration->migrateLegacy(); }

	public function down(): void {
		throw new MigrationException('Not implemented');
	}
}
