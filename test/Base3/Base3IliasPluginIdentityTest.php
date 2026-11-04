<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Api\IPlugin;
use Base3Ilias\Base3IliasPlugin;
use PHPUnit\Framework\TestCase;

final class Base3IliasPluginIdentityTest extends TestCase {

	public function testCompositionRootUsesTheDeclaredPluginIdentity(): void {
		self::assertTrue(is_subclass_of(Base3IliasPlugin::class, IPlugin::class));
		self::assertSame('base3iliasplugin', Base3IliasPlugin::getName());
	}
}
