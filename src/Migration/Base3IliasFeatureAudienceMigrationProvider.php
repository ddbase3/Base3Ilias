<?php declare(strict_types=1);

namespace Base3Ilias\Migration;

use Base3\Migration\Api\IDatabaseMigrationProvider;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;

/** Versioned format migrations for the native ILIAS-backed BASE3 feature settings area. */
final class Base3IliasFeatureAudienceMigrationProvider implements IDatabaseMigrationProvider {

	public function __construct(private readonly Base3IliasFeatureConfiguration $configuration) {}

	public static function getName(): string { return 'base3iliasfeatureaudiencemigrationprovider'; }

	public function isActive(): bool { return true; }

	public function getMigrations(): array {
		return [new Migration001ConvertFeatureGrants($this->configuration)];
	}
}
