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

use Base3\CoreFoundation\Api\IPlugin;
use Base3\Base3Ilias\Base3IliasPlugin;
use PHPUnit\Framework\TestCase;

final class Base3IliasPluginIdentityTest extends TestCase {

	public function testCompositionRootUsesTheDeclaredPluginIdentity(): void {
		self::assertTrue(is_subclass_of(Base3IliasPlugin::class, IPlugin::class));
		self::assertSame('base3iliasplugin', Base3IliasPlugin::getName());
	}
}
