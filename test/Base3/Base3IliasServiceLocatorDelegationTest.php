<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Api\IContainer;
use Base3Ilias\Base3\Base3IliasServiceLocator;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Base3IliasServiceLocatorContainerFixture.php';

final class Base3IliasServiceLocatorDelegationTest extends TestCase {

	public function testHostOnlyServiceIsResolvedThroughIliasContainer(): void {
		$value = new \stdClass();
		$locator = new Base3IliasServiceLocator();
		$locator->setIliasContainer(new Base3IliasServiceLocatorContainerFixture(['ilias.native.example' => $value]));
		self::assertTrue($locator->has('ilias.native.example'));
		self::assertSame($value, $locator->get('ilias.native.example'));
	}

	public function testKnownBase3ServiceRemainsAuthoritativeOverHostNameCollision(): void {
		$local = new \stdClass();
		$host = new \stdClass();
		$locator = new Base3IliasServiceLocator();
		$locator->set('base3.local.example', $local, IContainer::SHARED);
		$locator->setIliasContainer(new Base3IliasServiceLocatorContainerFixture(['base3.local.example' => $host]));
		self::assertTrue($locator->has('base3.local.example'));
		self::assertSame($local, $locator->get('base3.local.example'));
	}

	public function testUnknownServiceIsNeitherInventedNorAutocreated(): void {
		$locator = new Base3IliasServiceLocator();
		$locator->setIliasContainer(new Base3IliasServiceLocatorContainerFixture([]));
		self::assertFalse($locator->has('base3.missing.example'));
		self::assertSame(null, $locator->get('base3.missing.example'));
	}

	public function testServiceListCombinesBase3AndHostRegisteredNames(): void {
		$locator = new Base3IliasServiceLocator();
		$locator->set('base3.local.example', new \stdClass(), IContainer::SHARED);
		$locator->setIliasContainer(new Base3IliasServiceLocatorContainerFixture(['ilias.native.example' => new \stdClass()]));
		$names = $locator->getServiceList();
		self::assertContains('base3.local.example', $names);
		self::assertContains('ilias.native.example', $names);
	}
}
