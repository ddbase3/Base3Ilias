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
use Base3\Base3Ilias\Service\Base3IliasFeatureConfiguration;

/** Versioned format migrations for the native ILIAS-backed BASE3 feature settings area. */
final class Base3IliasFeatureAudienceMigrationProvider implements IDatabaseMigrationProvider {

	public function __construct(private readonly Base3IliasFeatureConfiguration $configuration) {}

	public static function getName(): string { return 'base3iliasfeatureaudiencemigrationprovider'; }

	public function isActive(): bool { return true; }

	public function getMigrations(): array {
		return [new Migration001ConvertFeatureGrants($this->configuration)];
	}
}
