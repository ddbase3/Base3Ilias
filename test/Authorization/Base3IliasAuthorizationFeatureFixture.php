<?php declare(strict_types=1);

namespace Base3Ilias\Test\Authorization;

use Base3\Authorization\Api\IFeature;
use Base3\Usermanager\Permission;

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
