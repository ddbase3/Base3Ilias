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


namespace Base3\Base3Ilias\Test\Authorization;

use Base3\CoreFoundation\Authorization\Api\IFeature;
use Base3\CoreFoundation\Usermanager\Permission;

final class Base3IliasAuthorizationFeatureFixture implements IFeature {

	public static function getName(): string { return 'base3iliasauthorizationfeaturefixture'; }

	public function getLabel(): string { return 'Authorization test feature'; }

	public function getDescription(): string { return 'A feature with one protected action'; }

	public function getPermissions(): array {
		return [Permission::for('feature', 'read_dashboard')];
	}

	public function getDefaultAudience(): array {
		return ['group' => 'development'];
	}
}
