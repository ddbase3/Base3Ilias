<?php declare(strict_types=1);

namespace Base3Ilias\Test\Service;

use Base3\Authorization\Api\IFeature;
use Base3\Usermanager\Permission;

final class Base3IliasServiceFeatureFixture implements IFeature {

	public static function getName(): string { return 'base3iliasservicefeaturefixture'; }
	public function getLabel(): string { return 'Service feature'; }
	public function getDescription(): string { return 'Read and edit reports'; }
	public function getPermissions(): array {
		return [Permission::for('reports', 'read'), Permission::for('reports', 'edit')];
	}
	public function getDefaultAudience(): array { return ['group' => 'presentation']; }
}
