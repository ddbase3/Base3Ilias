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


namespace Base3\Base3Ilias\Test\SettingsTab;

use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\Base3Ilias\SettingsTab\IliasSettingsTab;
use PHPUnit\Framework\TestCase;

final class IliasSettingsTabOrderTest extends TestCase {

	public function testRootSettingsOrder(): void {
		$tab = new IliasSettingsTab($this->createStub(ITranslation::class));
		self::assertNull($tab->getParentTabName());
		self::assertSame(5, $tab->getOrder());
	}
}
