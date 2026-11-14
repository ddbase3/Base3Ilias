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


namespace Base3\Base3Ilias\Test\Display;

use Base3\CoreFoundation\Api\IDisplay;
use Base3\Base3Ilias\Display\IliasConfigAdminDisplay;
use Base3\Base3Ilias\Display\IliasDashboardDisplay;
use Base3\Base3Ilias\Display\IliasErrorLogAdminDisplay;
use Base3\Base3Ilias\Display\IliasLogAdminDisplay;
use Base3\Base3Ilias\Display\IliasObjectDebugDisplay;
use Base3\Base3Ilias\Display\IliasPermissionDebugDisplay;
use Base3\Base3Ilias\Display\IliasRequestDebugDisplay;
use Base3\Base3Ilias\Display\IliasSystemHealthDisplay;
use Base3\Base3Ilias\Display\IliasUserDebugDisplay;
use PHPUnit\Framework\TestCase;

/** The class map uses these stable names to discover ILIAS administration displays. */
final class Base3IliasDisplayIdentifiersTest extends TestCase {

	public function testEveryIliasDisplayHasUniqueDiscoverableNameAndUsesTheDisplayContract(): void {
		$classes = [
			IliasConfigAdminDisplay::class, IliasDashboardDisplay::class,
			IliasErrorLogAdminDisplay::class, IliasLogAdminDisplay::class,
			IliasObjectDebugDisplay::class, IliasPermissionDebugDisplay::class,
			IliasRequestDebugDisplay::class, IliasSystemHealthDisplay::class,
			IliasUserDebugDisplay::class
		];
		$names = [];
		foreach ($classes as $class) {
			self::assertTrue(is_subclass_of($class, IDisplay::class));
			$name = $class::getName();
			self::assertSame(strtolower((new \ReflectionClass($class))->getShortName()), $name);
			$names[] = $name;
		}
		self::assertCount(count($classes), array_unique($names));
	}
}
