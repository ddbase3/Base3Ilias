<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasSession;
use PHPUnit\Framework\TestCase;

final class Base3IliasSessionTest extends TestCase {

	public function testHostSessionIsNotStartedOrReplacedByBase3(): void {
		$session = new Base3IliasSession();
		self::assertSame(session_status() === PHP_SESSION_ACTIVE, $session->started());
		self::assertSame($session->started(), $session->start());
		self::assertSame(session_id(), $session->getId());
	}
}
