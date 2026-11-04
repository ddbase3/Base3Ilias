<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasModuleRegistryService;
use Base3Ilias\Test\Fixture\Base3IliasRegistryFixture;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../Fixture/Base3IliasRegistryFixture.php';

final class Base3IliasModuleRegistryServiceTest extends TestCase {

	public function testListsAndResolvesDiscoveredModules(): void {
		$fixture = new Base3IliasRegistryFixture();
		try {
			$fixture->addModule('ModuleB', '2.0');
			$fixture->addModule('ModuleA', '1.0');
			$service = new Base3IliasModuleRegistryService($fixture->registry());
			self::assertSame(['ModuleA', 'ModuleB'], $service->getModuleNames());
			self::assertSame($fixture->root . '/ModuleA', $service->getModulePath('ModuleA'));
			self::assertSame($fixture->root . '/ModuleB', $service->requireModulePath('ModuleB'));
			self::assertNull($service->getModulePath('Unknown'));
		} finally {
			$fixture->remove();
		}
	}

	public function testRequireUnknownModuleFailsExplicitly(): void {
		$fixture = new Base3IliasRegistryFixture();
		try {
			$service = new Base3IliasModuleRegistryService($fixture->registry());
			$this->expectException(RuntimeException::class);
			$service->requireModulePath('Missing');
		} finally {
			$fixture->remove();
		}
	}
}
