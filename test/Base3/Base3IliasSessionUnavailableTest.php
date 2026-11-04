<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasSession;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class Base3IliasSessionUnavailableTest extends TestCase {

	public function testUnavailableHostSessionDoesNotReadOrCreateValues(): void {
		$session = $this->unavailableSession();
		self::assertSame('missing', $session->get('token', 'missing'));
		self::assertFalse($session->has('token'));
		$session->set('token', 'secret');
		self::assertSame('missing', $session->get('token', 'missing'));
		$session->remove('token');
		self::assertFalse($session->has('token'));
	}

	public function testUnavailableHostSessionCannotBeDestroyedByBase3(): void {
		self::assertFalse($this->unavailableSession()->destroy());
	}

	public function testStartOnlyReportsNativePhpSessionState(): void {
		$session = $this->unavailableSession();
		self::assertSame(session_status() === PHP_SESSION_ACTIVE, $session->started());
		self::assertSame($session->started(), $session->start());
	}

	public function testSessionIdDoesNotInventAnIndependentBase3Session(): void {
		self::assertSame(session_id(), $this->unavailableSession()->getId());
	}

	private function unavailableSession(): Base3IliasSession {
		$instance = (new ReflectionClass(Base3IliasSession::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Base3IliasSession::class, 'available'))->setValue($instance, false);
		return $instance;
	}
}
