<?php declare(strict_types=1);

namespace Base3Ilias\Test\Authorization;

use Base3\Authorization\Dto\ResourceReference;
use Base3\Authorization\Dto\SubjectReference;
use Base3Ilias\Authorization\Base3IliasAuthorizationAdministrationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class Base3IliasAuthorizationAdministrationBoundaryTest extends TestCase {

	public function testForeignAuthorityCannotDescribeNativePermissionCatalog(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown ILIAS authorization authority.');
		$this->service()->describe('other');
	}

	public function testWrongNativeResourceTypeIsRejectedBeforeAuthorization(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Expected native ILIAS ref_id resource.');
		$this->service()->describe('ilias', new ResourceReference('ilias', 'object_id', 42));
	}

	public function testZeroNativeReferenceIdCannotBeUsedToReadGrants(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Expected native ILIAS ref_id resource.');
		$this->service()->getSubjects(new ResourceReference('ilias', 'ref_id', 0));
	}

	public function testForeignAuthorityCannotListNativeOperations(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service()->getPermissions('other');
	}

	public function testInvalidNativeReferenceIsRejectedBeforeGrantMutation(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Expected native ILIAS ref_id resource.');
		$this->service()->replaceGrants(
			new ResourceReference('ilias', 'ref_id', 'invalid'),
			new SubjectReference('ilias', 'role', 8),
			[]
		);
	}

	private function service(): Base3IliasAuthorizationAdministrationService {
		return (new ReflectionClass(Base3IliasAuthorizationAdministrationService::class))->newInstanceWithoutConstructor();
	}
}
