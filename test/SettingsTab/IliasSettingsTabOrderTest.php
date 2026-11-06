<?php declare(strict_types=1);

namespace Base3Ilias\Test\SettingsTab;

use Base3\Translation\Api\ITranslation;
use Base3Ilias\SettingsTab\IliasSettingsTab;
use PHPUnit\Framework\TestCase;

final class IliasSettingsTabOrderTest extends TestCase {

	public function testRootSettingsOrder(): void {
		$tab = new IliasSettingsTab($this->createStub(ITranslation::class));
		self::assertNull($tab->getParentTabName());
		self::assertSame(5, $tab->getOrder());
	}
}
