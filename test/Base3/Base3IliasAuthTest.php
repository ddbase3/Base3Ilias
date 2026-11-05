<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasAuth;
use PHPUnit\Framework\TestCase;

final class Base3IliasAuthTest extends TestCase {

	public function testAuthenticationAdapterHasStableDiscoveryName(): void {
		self::assertSame('base3iliasauth', Base3IliasAuth::getName());
	}

	public function testAnonymousIliasAccountIsNotTreatedAsAuthenticated(): void {
		$session = $this->createMock(\ilAuthSession::class);
		$session->method('getUserId')->willReturn(13);
		self::assertSame(null, (new Base3IliasAuth($session))->login());
	}

	public function testAuthenticatedIliasAccountKeepsItsNativeIdentity(): void {
		$session = $this->createMock(\ilAuthSession::class);
		$session->method('getUserId')->willReturn(427);
		self::assertSame(427, (new Base3IliasAuth($session))->login());
	}

	public function testProvidedCredentialsCannotAuthenticateAnonymousIliasSession(): void {
		$session = $this->createMock(\ilAuthSession::class);
		$session->method('getUserId')->willReturn(13);
		self::assertNull((new Base3IliasAuth($session))->login(['username' => 'other-user']));
	}

	public function testProvidedCredentialsDoNotOverrideIliasSessionIdentity(): void {
		$session = $this->createMock(\ilAuthSession::class);
		$session->method('getUserId')->willReturn(427);
		self::assertSame(427, (new Base3IliasAuth($session))->login(['username' => 'other-user']));
	}
}
