<?php declare(strict_types=1);

namespace Base3Ilias\Migration;

use Base3\Migration\Api\IDatabaseMigrationProvider;
use Base3Ilias\Service\Base3IliasGroupConfiguration;

/** Owns versioned conversion of Base3Ilias group configuration stored in the active ILIAS settings backend. */
final class Base3IliasGroupCriteriaMigrationProvider implements IDatabaseMigrationProvider {

	public function __construct(private readonly Base3IliasGroupConfiguration $configuration) {}

	public static function getName(): string { return 'base3iliasgroupcriteriamigrationprovider'; }

	public function isActive(): bool { return true; }

	public function getMigrations(): array {
		return [new Migration001ConvertGroupAssignments($this->configuration)];
	}
}
