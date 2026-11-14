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


namespace Base3\Base3Ilias\Test\Ilias;

use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\Base3Ilias\Base3\Base3IliasRuntime;
use Base3\Base3Ilias\Base3\Base3IliasServiceLocator;
use Base3\Base3Ilias\Event\IliasApplicationEvent;
use Base3\Base3Ilias\Ilias\ApplicationEventDispatcher;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

final class ApplicationEventDispatcherTest extends TestCase {

	public function testForwardsNativeComponentEventWithOriginalNameAndPayload(): void {
		$payload = ['ref_id' => 17, 'settings' => ['nested' => true], 'user_id' => null];
		$events = $this->createMock(IEventManager::class);
		$events->expects(self::once())->method('fire')->willReturnCallback(
			static function(object|string $event, mixed ...$arguments) use ($payload): array {
				self::assertInstanceOf(IliasApplicationEvent::class, $event);
				self::assertSame('components/ILIAS/ILIASObject', $event->component);
				self::assertSame('afterUpdate', $event->name);
				self::assertSame($payload, $event->parameters);
				self::assertSame([], $arguments);
				return [];
			}
		);
		$this->dispatchWithEventManager($events, static function() use ($payload): void {
			ApplicationEventDispatcher::handleEvent('components/ILIAS/ILIASObject', 'afterUpdate', $payload);
		});
	}

	public function testEmptyEventParametersRemainEmpty(): void {
		$events = $this->createMock(IEventManager::class);
		$events->expects(self::once())->method('fire')->willReturnCallback(
			static function(object|string $event): array {
				self::assertInstanceOf(IliasApplicationEvent::class, $event);
				self::assertSame([], $event->parameters);
				self::assertSame('empty', $event->name);
				return [];
			}
		);
		$this->dispatchWithEventManager($events, static function(): void {
			ApplicationEventDispatcher::handleEvent('native', 'empty', []);
		});
	}

	public function testListenerExceptionsAreNotSwallowedAtTheIliasBoundary(): void {
		$events = $this->createMock(IEventManager::class);
		$events->expects(self::once())->method('fire')->willThrowException(new RuntimeException('listener failed'));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('listener failed');
		$this->dispatchWithEventManager($events, static function(): void {
			ApplicationEventDispatcher::handleEvent('native', 'failed', []);
		});
	}

	private function dispatchWithEventManager(IEventManager $events, callable $action): void {
		$locator = $this->createMock(Base3IliasServiceLocator::class);
		$locator->expects(self::once())->method('get')->with(IEventManager::class)->willReturn($events);
		$booted = new ReflectionProperty(Base3IliasRuntime::class, 'booted');
		$serviceLocator = new ReflectionProperty(Base3IliasRuntime::class, 'serviceLocator');
		$previousBooted = $booted->getValue();
		$previousLocator = $serviceLocator->getValue();
		try {
			$booted->setValue(null, true);
			$serviceLocator->setValue(null, $locator);
			$action();
		}
		finally {
			$serviceLocator->setValue(null, $previousLocator);
			$booted->setValue(null, $previousBooted);
		}
	}
}
