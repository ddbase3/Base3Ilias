<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Api\IBootstrap;
use Base3\ServiceSelector\Api\IServiceSelector;
use Base3Ilias\Base3\Base3IliasBootstrap;
use Base3Ilias\Base3\Base3IliasRuntime;
use Base3Ilias\Base3\Base3IliasServiceLocator;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class Base3IliasBootstrapTest extends TestCase {

	public function testBootstrapRunsExistingRuntimeDispatchAndWritesItsResponse(): void {
		$selector = $this->createMock(IServiceSelector::class);
		$selector->expects(self::once())->method('go')->willReturn('BASE3 dispatch result');

		$locator = $this->createMock(Base3IliasServiceLocator::class);
		$locator->expects(self::once())->method('get')
			->with(IServiceSelector::class)->willReturn($selector);

		$properties = [];
		foreach (['booted', 'serviceLocator', 'finishDispatched', 'standaloneRequestSnapshot'] as $name) {
			$properties[$name] = new ReflectionProperty(Base3IliasRuntime::class, $name);
		}
		$original = [];
		foreach ($properties as $name => $property) {
			$original[$name] = $property->getValue();
		}

		try {
			$properties['booted']->setValue(null, true);
			$properties['serviceLocator']->setValue(null, $locator);
			$properties['finishDispatched']->setValue(null, true);
			$properties['standaloneRequestSnapshot']->setValue(null, null);

			$bootstrap = new Base3IliasBootstrap();
			self::assertInstanceOf(IBootstrap::class, $bootstrap);
			ob_start();
			try {
				$bootstrap->run();
				$output = ob_get_clean();
			} catch (\Throwable $error) {
				ob_end_clean();
				throw $error;
			}
			self::assertSame('BASE3 dispatch result', $output);
		} finally {
			foreach ($properties as $name => $property) {
				$property->setValue(null, $original[$name]);
			}
		}
	}
}
