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


namespace Base3\Base3Ilias\Test\Event;

use Base3\Base3Ilias\Event\IliasApplicationEvent;
use PHPUnit\Framework\TestCase;

final class IliasApplicationEventTest extends TestCase {

	public function testNativeEventIdentityAndPayloadAreUnchanged(): void {
		$parameters = ['ref_id' => 123, 'obj_id' => 456];
		$event = new IliasApplicationEvent('components/ILIAS/ILIASObject', 'toTrash', $parameters);
		self::assertSame('components/ILIAS/ILIASObject', $event->component);
		self::assertSame('toTrash', $event->name);
		self::assertSame($parameters, $event->parameters);
	}
}
