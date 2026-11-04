<?php declare(strict_types=1);

namespace Base3Ilias\Test\External;

use Base3Ilias\External\IliasPsrContainer;
use ILIAS\DI\Container;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class IliasPsrContainerTest extends TestCase {

	public function testImplementsThePsrContainerContractAndDelegatesServiceLookup(): void {
		$service = new \stdClass();
		$ilias = $this->createMock(Container::class);
		$ilias->expects(self::once())->method('offsetExists')->with('repository')->willReturn(true);
		$ilias->expects(self::once())->method('offsetGet')->with('repository')->willReturn($service);
		$adapter = new IliasPsrContainer($ilias);
		self::assertInstanceOf(ContainerInterface::class, $adapter);
		self::assertSame($service, $adapter->get('repository'));
	}

	public function testMissingNativeServiceFailsWithoutAttemptingToResolveIt(): void {
		$ilias = $this->createMock(Container::class);
		$ilias->expects(self::once())->method('offsetExists')->with('missing')->willReturn(false);
		$ilias->expects(self::never())->method('offsetGet');
		$adapter = new IliasPsrContainer($ilias);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("Service 'missing' not found in ILIAS container");
		$adapter->get('missing');
	}

	public function testHasDoesNotInstantiateNativeServices(): void {
		$ilias = $this->createMock(Container::class);
		$ilias->expects(self::exactly(2))->method('offsetExists')
			->willReturnCallback(static fn(string $key): bool => $key === 'available');
		$ilias->expects(self::never())->method('offsetGet');
		$adapter = new IliasPsrContainer($ilias);
		self::assertTrue($adapter->has('available'));
		self::assertFalse($adapter->has('absent'));
	}

	public function testReturnsNullServiceWhenTheNativeContainerReportsItAsPresent(): void {
		$ilias = $this->createMock(Container::class);
		$ilias->method('offsetExists')->willReturn(true);
		$ilias->expects(self::once())->method('offsetGet')->willReturn(null);
		self::assertNull((new IliasPsrContainer($ilias))->get('nullable'));
	}

	public function testListsNativeContainerKeysWithoutReorderingOrFiltering(): void {
		$ilias = $this->createMock(Container::class);
		$ilias->expects(self::once())->method('keys')->willReturn(['database', 'rbac', 'http']);
		self::assertSame(['database', 'rbac', 'http'], (new IliasPsrContainer($ilias))->keys());
	}

	public function testAnEmptyNativeContainerHasNoServices(): void {
		$ilias = $this->createMock(Container::class);
		$ilias->method('offsetExists')->willReturn(false);
		$ilias->method('keys')->willReturn([]);
		$adapter = new IliasPsrContainer($ilias);
		self::assertSame([], $adapter->keys());
		self::assertFalse($adapter->has('anything'));
	}
}
