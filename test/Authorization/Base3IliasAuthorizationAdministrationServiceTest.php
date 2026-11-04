<?php declare(strict_types=1);

namespace Base3Ilias\Test\Authorization;

use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Authorization\Dto\ResourceReference;
use Base3\Authorization\Dto\SubjectReference;
use Base3Ilias\Authorization\Base3IliasAuthorizationAdministrationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ilDBInterface;
use ilRbacAdmin;
use ilRbacReview;

final class Base3IliasAuthorizationAdministrationServiceTest extends TestCase {

	public function testGlobalCapabilitiesRequireNativeSystemAdministration(): void {
		foreach ([false, true] as $allowed) {
			$authorization = $this->createMock(IAuthorizationService::class);
			$authorization->expects(self::once())->method('decide')->willReturnCallback(static function(AuthorizationRequest $request) use ($allowed): AuthorizationDecision {
				self::assertSame('ilias', $request->authority);
				self::assertSame('system', $request->permission->scope);
				self::assertSame('admin', $request->permission->permission);
				self::assertNull($request->resource);
				return $allowed ? AuthorizationDecision::allow() : AuthorizationDecision::deny();
			});
			$capabilities = $this->service($authorization)->describe('ilias');
			self::assertSame('ilias', $capabilities->authority);
			self::assertSame($allowed, $capabilities->canRead);
			self::assertSame($allowed, $capabilities->canWrite);
			self::assertTrue($capabilities->supportsHierarchy);
			self::assertSame(['role'], $capabilities->subjectKinds);
			self::assertNotEmpty($capabilities->limitations);
		}
	}

	public function testResourceCapabilitiesDependOnNativeEditPermissionOnly(): void {
		$resource = $this->resource(47);
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->expects(self::once())->method('decide')->willReturnCallback(static function(AuthorizationRequest $request) use ($resource): AuthorizationDecision {
			self::assertSame($resource, $request->resource);
			self::assertSame('ilias', $request->permission->scope);
			self::assertSame('edit_permission', $request->permission->permission);
			return AuthorizationDecision::deny();
		});
		$capabilities = $this->service($authorization)->describe('ilias', $resource);
		self::assertFalse($capabilities->canRead);
		self::assertFalse($capabilities->canWrite);
	}

	public function testDeniedOperatorCannotReadGlobalPermissionCatalog(): void {
		$review = $this->createMock(ilRbacReview::class);
		$review->expects(self::never())->method('getOperations');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Not permitted to inspect native ILIAS role grants.');
		$this->service($this->authorization(false), $review)->getPermissions('ilias');
	}

	public function testGlobalCatalogFiltersMissingIdentifiersAndBlankOperations(): void {
		$review = $this->createMock(ilRbacReview::class);
		$review->expects(self::once())->method('getOperations')->willReturn([
			['ops_id' => 1, 'operation' => 'read', 'description' => 'Read object'],
			['ops_id' => '3', 'operation' => ' write ', 'description' => 'Modify object'],
			['ops_id' => 0, 'operation' => 'create'],
			['ops_id' => 4, 'operation' => '  '],
			['operation' => 'delete'],
			[]
		]);
		$permissions = $this->service($this->authorization(true), $review)->getPermissions('ilias');
		self::assertCount(2, $permissions);
		self::assertSame(1, $permissions[0]->id);
		self::assertSame('read', $permissions[0]->permission);
		self::assertSame('Read object', $permissions[0]->info);
		self::assertSame(3, $permissions[1]->id);
		self::assertSame('write', $permissions[1]->permission);
		self::assertSame('ilias', $permissions[1]->scope);
	}

	public function testResourceSubjectListingIsGuardedBeforeNativeReview(): void {
		$resource = $this->resource(34);
		$review = $this->createMock(ilRbacReview::class);
		$review->expects(self::never())->method('getParentRoleIds');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Not permitted to inspect native ILIAS role grants.');
		$this->service($this->authorization(false), $review)->getSubjects($resource);
	}

	public function testEmptyNativeRoleListDoesNotInventSyntheticSubjects(): void {
		$resource = $this->resource(34);
		$review = $this->createMock(ilRbacReview::class);
		$review->expects(self::once())->method('getParentRoleIds')->with(34, false)->willReturn([]);
		self::assertSame([], $this->service($this->authorization(true), $review)->getSubjects($resource));
	}

	public function testWriteDenialPrecedesNativeRoleInspectionAndDatabaseChanges(): void {
		$resource = $this->resource(34);
		$review = $this->createMock(ilRbacReview::class);
		$review->expects(self::never())->method('getParentRoleIds');
		$admin = $this->createMock(ilRbacAdmin::class);
		$admin->expects(self::never())->method('grantPermission');
		$database = $this->createMock(ilDBInterface::class);
		$database->expects(self::never())->method('beginTransaction');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Native edit_permission is required');
		$this->service($this->authorization(false), $review, $admin, $database)
			->replaceGrants($resource, new SubjectReference('ilias', 'role', 7), []);
	}

	public function testOnlyNumericNativeRolesCanBeModified(): void {
		$review = $this->createMock(ilRbacReview::class);
		$review->expects(self::never())->method('getParentRoleIds');
		$admin = $this->createMock(ilRbacAdmin::class);
		$admin->expects(self::never())->method('grantPermission');
		$database = $this->createMock(ilDBInterface::class);
		$database->expects(self::never())->method('beginTransaction');
		$service = $this->service($this->authorization(true), $review, $admin, $database);
		foreach ([
			new SubjectReference('another', 'role', 7),
			new SubjectReference('ilias', 'user', 7),
			new SubjectReference('ilias', 'role', 'not-a-number'),
			new SubjectReference('ilias', 'role', 0),
			new SubjectReference('ilias', 'role', -1)
		] as $subject) {
			try {
				$service->replaceGrants($this->resource(34), $subject, []);
				self::fail('Invalid native role identifier was accepted.');
			}
			catch (InvalidArgumentException $error) {
				self::assertStringContainsString('numeric native role ID', $error->getMessage());
			}
		}
	}

	public function testRoleNotReturnedByNativeReviewCanNeverReceiveGrants(): void {
		$review = $this->createMock(ilRbacReview::class);
		$review->expects(self::once())->method('getParentRoleIds')->with(34, false)->willReturn([]);
		$admin = $this->createMock(ilRbacAdmin::class);
		$admin->expects(self::never())->method('grantPermission');
		$database = $this->createMock(ilDBInterface::class);
		$database->expects(self::never())->method('beginTransaction');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('The native role is not editable');
		$this->service($this->authorization(true), $review, $admin, $database)
			->replaceGrants($this->resource(34), new SubjectReference('ilias', 'role', 19), []);
	}

	public function testInvalidResourcesAreRejectedBeforeNativeAdminOperations(): void {
		$service = $this->service($this->authorization(true));
		foreach ([
			new ResourceReference('other', 'ref_id', 34),
			new ResourceReference('ilias', 'object_id', 34),
			new ResourceReference('ilias', 'ref_id', '14x'),
			new ResourceReference('ilias', 'ref_id', 0)
		] as $resource) {
			try {
				$service->replaceGrants($resource, new SubjectReference('ilias', 'role', 7), []);
				self::fail('Invalid native resource was accepted.');
			}
			catch (InvalidArgumentException $error) {
				self::assertNotSame('', $error->getMessage());
			}
		}
	}

	private function authorization(bool $allowed): IAuthorizationService {
		$service = $this->createMock(IAuthorizationService::class);
		$service->method('decide')->willReturn($allowed ? AuthorizationDecision::allow() : AuthorizationDecision::deny());
		return $service;
	}

	private function resource(int|string $id): ResourceReference {
		return new ResourceReference('ilias', 'ref_id', $id);
	}

	private function service(
		IAuthorizationService $authorization,
		?ilRbacReview $review = null,
		?ilRbacAdmin $admin = null,
		?ilDBInterface $database = null
	): Base3IliasAuthorizationAdministrationService {
		return new Base3IliasAuthorizationAdministrationService(
			$authorization,
			$review ?? $this->createMock(ilRbacReview::class),
			$admin ?? $this->createMock(ilRbacAdmin::class),
			$database ?? $this->createMock(ilDBInterface::class)
		);
	}
}
