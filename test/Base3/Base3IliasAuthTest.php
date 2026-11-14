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


namespace Base3\Base3Ilias\Test\Base3;

use Base3\Base3Ilias\Base3\Base3IliasAuth;
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
