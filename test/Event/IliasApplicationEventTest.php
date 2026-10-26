<?php declare(strict_types=1);

namespace Base3Ilias\Test\Event;

use Base3Ilias\Event\IliasApplicationEvent;
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
