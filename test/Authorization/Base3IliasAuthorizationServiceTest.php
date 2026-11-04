<?php declare(strict_types=1);

namespace Base3Ilias\Test\Authorization;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Api\IClassMap;
use Base3\Authorization\Api\IFeature;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Authorization\Dto\ResourceReference;
use Base3\Authorization\Dto\SubjectReference;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Group;
use Base3\Usermanager\Permission;
use Base3\Usermanager\Role;
use Base3\Usermanager\User;
use Base3\Usermanager\UserCriterionEvaluator;
use Base3Ilias\Authorization\Base3IliasAuthorizationService;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ilRbacSystem;

require_once __DIR__ . '/Base3IliasAuthorizationFeatureFixture.php';

final class Base3IliasAuthorizationServiceTest extends TestCase {

	public function testAnonymousAndInvalidNativeSubjectsNeverReachRbac(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::never())->method('checkAccessOfUser');
		$access = $this->createMock(IAccesscontrol::class);
		$access->method('getUserId')->willReturn(null);
		$service = $this->service($rbac, $access);

		foreach ([
			new AuthorizationRequest('ilias', Permission::for('ilias', 'read'), $this->resource(17)),
			new AuthorizationRequest('ilias', Permission::for('ilias', 'read'), $this->resource(17), new SubjectReference('elsewhere', 'user', 41)),
			new AuthorizationRequest('ilias', Permission::for('ilias', 'read'), $this->resource(17), new SubjectReference('ilias', 'role', 41)),
			new AuthorizationRequest('ilias', Permission::for('ilias', 'read'), $this->resource(17), new SubjectReference('ilias', 'user', 0))
		] as $request) {
			$decision = $service->decide($request);
			self::assertSame('deny', $decision->outcome);
			self::assertSame('unknown_subject', $decision->reasonCode);
		}
	}

	public function testRejectsUnsupportedScopesAndMissingOrInvalidNativeReferences(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::never())->method('checkAccessOfUser');
		$service = $this->service($rbac);
		$cases = [
			[Permission::for('users', 'read'), $this->resource(17), 'unsupported_scope'],
			[Permission::for('ilias', 'read'), null, 'ref_id_required'],
			[Permission::for('ilias', 'read'), new ResourceReference('ilias', 'object_id', 17), 'ref_id_required'],
			[Permission::for('ilias', 'read'), $this->resource('0'), 'invalid_ref_id'],
			[Permission::for('ilias', 'read'), $this->resource('-7'), 'invalid_ref_id'],
			[Permission::for('ilias', 'read'), $this->resource('17x'), 'invalid_ref_id']
		];
		foreach ($cases as [$permission, $resource, $reason]) {
			$decision = $service->decide(new AuthorizationRequest('ilias', $permission, $resource));
			self::assertSame('deny', $decision->outcome);
			self::assertSame($reason, $decision->reasonCode);
		}
	}

	public function testInvalidNativeOperationNamesAreRejectedBeforeNativeChecks(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::never())->method('checkAccessOfUser');
		$service = $this->service($rbac);
		foreach (['read,,write', 'read,', ',read', 'read|write', 'read;delete', 'read write', '1read', 'read\nwrite', ''] as $operation) {
			if ($operation === '') continue;
			$decision = $service->decide(new AuthorizationRequest('ilias', Permission::for('ilias', $operation), $this->resource(14)));
			self::assertSame('deny', $decision->outcome, $operation);
			self::assertSame('invalid_native_operation', $decision->reasonCode, $operation);
		}
	}

	public function testNativeChecksPreserveExplicitIdentityAndNormalizedOperations(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::once())->method('checkAccessOfUser')
			->with(72, 'read,write', 18, '')->willReturn(true);
		$service = $this->service($rbac);
		$decision = $service->decide(new AuthorizationRequest(
			'ilias', Permission::for(' ILIAS ', ' Read , WRITE '), $this->resource('18'),
			new SubjectReference('ilias', 'user', '72')
		));
		self::assertTrue($decision->isAllowed());
		self::assertSame('native_rbac', $decision->reasonCode);
	}

	public function testNativeDenialIsNotConvertedToIndeterminateOrAllow(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::once())->method('checkAccessOfUser')->with(23, 'read', 17, '')->willReturn(false);
		$decision = $this->service($rbac)->decide(new AuthorizationRequest('ilias', Permission::for('ilias', 'read'), $this->resource(17)));
		self::assertSame('deny', $decision->outcome);
		self::assertSame('native_rbac', $decision->reasonCode);
	}

	public function testCreateRequiresNativeObjectTypeEvenWithOtherwiseValidPermission(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::never())->method('checkAccessOfUser');
		$service = $this->service($rbac);
		foreach ([[], ['object_type' => ''], ['object_type' => 123], ['object_type' => 'Course'], ['object_type' => 'course-name']] as $parameters) {
			$decision = $service->decide(new AuthorizationRequest('ilias', Permission::for('ilias', 'create,read'), $this->resource(42), null, $parameters));
			self::assertSame('deny', $decision->outcome);
			self::assertSame('native_create_type_required', $decision->reasonCode);
		}
	}

	public function testCreateDelegatesNativeTypeWithoutGrantingItImplicitly(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::once())->method('checkAccessOfUser')->with(23, 'read,create', 44, 'crs')->willReturn(false);
		$decision = $this->service($rbac)->decide(new AuthorizationRequest(
			'ilias', Permission::for('ilias', 'read,create'), $this->resource(44), null, ['object_type' => 'crs']
		));
		self::assertSame('deny', $decision->outcome);
	}

	public function testCurrentNativeAdministratorRequiresAnExplicitRoleMatch(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::never())->method('checkAccessOfUser');
		$users = $this->createMock(IUsermanager::class);
		$users->expects(self::exactly(2))->method('hasRole')
			->willReturnCallback(static function(Role $role): bool {
				self::assertSame('admin', $role->name);
				static $calls = 0;
				return ++$calls === 1;
			});
		$service = $this->service($rbac, null, $users);
		$request = new AuthorizationRequest('ilias', Permission::for('system', 'admin'));
		self::assertSame('allow', $service->decide($request)->outcome);
		self::assertSame('deny', $service->decide($request)->outcome);
	}

	public function testNativeAdministratorLookupForAnotherUserRequiresItsOwnRoleMetadata(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::never())->method('checkAccessOfUser');
		$users = $this->createMock(IUsermanager::class);
		$users->expects(self::never())->method('hasRole');
		$admin = User::fromArray(['id' => 72, 'roles' => [Role::named('student'), Role::named('admin')]]);
		$nonAdmin = User::fromArray(['id' => 73, 'roles' => [Role::named('student')]]);
		$users->method('getUserById')->willReturnCallback(static fn(int|string $id): ?User => match ((int)$id) {
			72 => $admin,
			73 => $nonAdmin,
			default => null
		});
		$service = $this->service($rbac, null, $users);
		foreach ([72 => 'allow', 73 => 'deny', 74 => 'deny'] as $id => $outcome) {
			$request = new AuthorizationRequest('ilias', Permission::for('system', 'admin'), null, new SubjectReference('ilias', 'user', $id));
			self::assertSame($outcome, $service->decide($request)->outcome);
		}
	}

	public function testSystemAdminPermissionOnResourceIsNotAnAdminShortcut(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::never())->method('checkAccessOfUser');
		$decision = $this->service($rbac)->decide(new AuthorizationRequest('ilias', Permission::for('system', 'admin'), $this->resource(13)));
		self::assertSame('deny', $decision->outcome);
		self::assertSame('unsupported_scope', $decision->reasonCode);
	}

	public function testNativeErrorsFailClosedAndDoNotLeakExceptionMessages(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->method('checkAccessOfUser')->willThrowException(new \RuntimeException('secret native error'));
		$logger = $this->createMock(ILogger::class);
		$logger->expects(self::once())->method('error')->with(
			'ILIAS authorization could not be evaluated.', ['exception_class' => \RuntimeException::class]
		);
		$decision = $this->service($rbac, null, null, null, $logger)->decide(
			new AuthorizationRequest('ilias', Permission::for('ilias', 'read'), $this->resource(17))
		);
		self::assertSame('indeterminate', $decision->outcome);
		self::assertSame('native_authority_error', $decision->reasonCode);
	}

	public function testBatchPreloadsUniqueNativeReferencesByResolvedUserAndPreservesKeys(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::exactly(2))->method('preloadRbacPaCache')
			->willReturnCallback(static function(array $ids, int $user): void {
				self::assertSame($user === 23 ? [17, 18] : [19], $ids);
			});
		$rbac->expects(self::exactly(4))->method('checkAccessOfUser')->willReturn(true);
		$requests = [
			'one' => new AuthorizationRequest('ilias', Permission::for('ilias', 'read'), $this->resource(17)),
			'two' => new AuthorizationRequest('ilias', Permission::for('ilias', 'write'), $this->resource(17)),
			'three' => new AuthorizationRequest('ilias', Permission::for('ilias', 'read'), $this->resource(18)),
			'other' => new AuthorizationRequest('ilias', Permission::for('ilias', 'read'), $this->resource(19), new SubjectReference('ilias', 'user', 31))
		];
		$decisions = $this->service($rbac)->decideMany($requests);
		self::assertSame(array_keys($requests), array_keys($decisions));
		foreach ($decisions as $decision) self::assertTrue($decision->isAllowed());
	}

	public function testBatchPreloadFailureDoesNotShortCircuitNativeChecks(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::once())->method('preloadRbacPaCache')->willThrowException(new \RuntimeException('preload unavailable'));
		$rbac->expects(self::once())->method('checkAccessOfUser')->with(23, 'read', 17, '')->willReturn(true);
		$logger = $this->createMock(ILogger::class);
		$logger->expects(self::once())->method('error')->with('ILIAS RBAC preloading failed.', ['exception_class' => \RuntimeException::class]);
		$decisions = $this->service($rbac, null, null, null, $logger)->decideMany([
			new AuthorizationRequest('ilias', Permission::for('ilias', 'read'), $this->resource(17))
		]);
		self::assertTrue($decisions[0]->isAllowed());
	}

	public function testFeaturePermissionsRequireCurrentUserAndGlobalTarget(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::never())->method('checkAccessOfUser');
		$service = $this->service($rbac);
		$permission = Permission::for('feature', 'read_dashboard');
		$globalOnly = $service->decide(new AuthorizationRequest('ilias', $permission, $this->resource(17)));
		self::assertSame('feature_is_global', $globalOnly->reasonCode);
		$anotherSubject = $service->decide(new AuthorizationRequest('ilias', $permission, null, new SubjectReference('ilias', 'user', 24)));
		self::assertSame('feature_subject_not_current', $anotherSubject->reasonCode);
	}

	public function testUnknownFeaturePermissionIsDeniedWithoutNativeRbac(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::never())->method('checkAccessOfUser');
		$decision = $this->service($rbac)->decide(new AuthorizationRequest('ilias', Permission::for('feature', 'missing')));
		self::assertSame('deny', $decision->outcome);
		self::assertSame('unknown_feature_permission', $decision->reasonCode);
	}

	public function testDuplicateFeaturePermissionFailsClosed(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::never())->method('checkAccessOfUser');
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([
			new Base3IliasAuthorizationFeatureFixture(), new Base3IliasAuthorizationFeatureFixture()
		]);
		$decision = $this->service($rbac, null, null, $map)->decide(new AuthorizationRequest('ilias', Permission::for('feature', 'read_dashboard')));
		self::assertSame('indeterminate', $decision->outcome);
		self::assertSame('duplicate_feature_permission', $decision->reasonCode);
	}

	public function testConfiguredEmptyFeatureAudienceDeniesAccessEvenForDevelopmentGroup(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::never())->method('checkAccessOfUser');
		$map = $this->featureMap();
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['rules' => ['base3iliasauthorizationfeaturefixture' => []]]);
		$groups = $this->createMock(IUsermanager::class);
		$groups->method('getGroups')->willReturn([Group::fromArray(['name' => 'development'])]);
		$decision = $this->service($rbac, null, $groups, $map, null, $store)->decide(new AuthorizationRequest('ilias', Permission::for('feature', 'read_dashboard')));
		self::assertSame('deny', $decision->outcome);
		self::assertSame('feature_audience_not_allowed', $decision->reasonCode);
	}

	public function testGroupBasedFeaturePermissionUsesTheSharedCriterionEvaluator(): void {
		$rbac = $this->createMock(ilRbacSystem::class);
		$rbac->expects(self::never())->method('checkAccessOfUser');
		$map = $this->featureMap();
		$users = $this->createMock(IUsermanager::class);
		$users->method('getGroups')->willReturn([Group::fromArray(['name' => 'development'])]);
		$users->method('getUser')->willReturn(User::fromArray(['id' => 23]));
		$decision = $this->service($rbac, null, $users, $map)->decide(new AuthorizationRequest('ilias', Permission::for('feature', 'read_dashboard')));
		self::assertSame('allow', $decision->outcome);
		self::assertSame('base3_feature_audience', $decision->reasonCode);
	}

	private function resource(int|string $id): ResourceReference {
		return new ResourceReference('ilias', 'ref_id', $id);
	}

	private function featureMap(): IClassMap {
		$map = $this->createMock(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new Base3IliasAuthorizationFeatureFixture()]);
		return $map;
	}

	private function service(
		ilRbacSystem $rbac,
		?IAccesscontrol $access = null,
		?IUsermanager $users = null,
		?IClassMap $map = null,
		?ILogger $logger = null,
		?ISettingsStore $store = null
	): Base3IliasAuthorizationService {
		if ($access === null) {
			$access = $this->createMock(IAccesscontrol::class);
			$access->method('getUserId')->willReturn(23);
		}
		$users ??= $this->createMock(IUsermanager::class);
		if ($map === null) {
			$map = $this->createMock(IClassMap::class);
			$map->method('getInstancesByInterface')->willReturn([]);
		}
		if ($store === null) {
			$store = $this->createMock(ISettingsStore::class);
			$store->method('get')->willReturn([]);
		}
		$evaluator = new UserCriterionEvaluator($map);
		$configuration = new Base3IliasFeatureConfiguration($store, $evaluator, $map);
		return new Base3IliasAuthorizationService(
			$access, $users, $map, $configuration, $evaluator, $rbac, $logger ?? $this->createMock(ILogger::class)
		);
	}
}
