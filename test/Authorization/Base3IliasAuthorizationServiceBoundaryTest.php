<?php declare(strict_types=1);

namespace Base3Ilias\Test\Authorization;

use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Authorization\Dto\SubjectReference;
use Base3\Usermanager\Permission;
use Base3Ilias\Authorization\Base3IliasAuthorizationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class Base3IliasAuthorizationServiceBoundaryTest extends TestCase {

	public function testDeclaresIliasAsItsAuthorizationAuthority(): void {
		self::assertSame('ilias', $this->service()->getDefaultAuthority());
	}

	public function testForeignAuthorityIsAlwaysIndeterminateBeforeNativeEvaluation(): void {
		$decision = $this->service()->decide(new AuthorizationRequest('other', Permission::for('system', 'admin')));
		self::assertFalse($decision->isAllowed());
		self::assertSame('indeterminate', $decision->outcome);
		self::assertSame('unknown_authority', $decision->reasonCode);
	}

	public function testDelegationIsDeniedBeforeNativeEvaluation(): void {
		$request = new AuthorizationRequest(
			'ilias',
			Permission::for('system', 'admin'),
			null,
			null,
			[],
			[],
			new SubjectReference('ilias', 'user', 17)
		);
		$decision = $this->service()->decide($request);
		self::assertFalse($decision->isAllowed());
		self::assertSame('deny', $decision->outcome);
		self::assertSame('delegation_not_supported', $decision->reasonCode);
	}

	public function testBatchRejectsInvalidRequestsWithoutNativePreloading(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Expected AuthorizationRequest.');
		$this->service()->decideMany([new AuthorizationRequest('other', Permission::for('system', 'admin')), null]);
	}

	public function testBatchPreservesKeysAndReturnsIndependentDeniedByDefaultDecisions(): void {
		$requests = [
			'first' => new AuthorizationRequest('other', Permission::for('system', 'admin')),
			'second' => new AuthorizationRequest('unregistered', Permission::for('ilias', 'read'))
		];
		$decisions = $this->service()->decideMany($requests);
		self::assertSame(['first', 'second'], array_keys($decisions));
		foreach ($decisions as $decision) {
			self::assertSame('indeterminate', $decision->outcome);
			self::assertSame('unknown_authority', $decision->reasonCode);
		}
	}

	private function service(): Base3IliasAuthorizationService {
		return (new ReflectionClass(Base3IliasAuthorizationService::class))->newInstanceWithoutConstructor();
	}
}
