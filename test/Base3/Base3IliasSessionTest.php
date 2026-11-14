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

use Base3\Base3Ilias\Base3\Base3IliasSession;
use PHPUnit\Framework\TestCase;

final class Base3IliasSessionTest extends TestCase {

	public function testHostSessionIsNotStartedOrReplacedByBase3(): void {
		$session = new Base3IliasSession();
		self::assertSame(session_status() === PHP_SESSION_ACTIVE, $session->started());
		self::assertSame($session->started(), $session->start());
		self::assertSame(session_id(), $session->getId());
	}
}
