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

use Base3\Base3Ilias\Base3\Base3IliasLanguage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Base3IliasLanguageUserFixture.php';
require_once __DIR__ . '/Base3IliasLanguageDicFixture.php';

final class Base3IliasLanguageIdentityTest extends TestCase {

	private bool $hadDic = false;
	private mixed $originalDic = null;

	protected function setUp(): void {
		parent::setUp();
		$this->hadDic = array_key_exists('DIC', $GLOBALS);
		$this->originalDic = $GLOBALS['DIC'] ?? null;
	}

	protected function tearDown(): void {
		if ($this->hadDic) $GLOBALS['DIC'] = $this->originalDic;
		else unset($GLOBALS['DIC']);
		parent::tearDown();
	}

	public function testCurrentLanguageIsReadFromNativeUserWithoutChangingIt(): void {
		$GLOBALS['DIC'] = new Base3IliasLanguageDicFixture(new Base3IliasLanguageUserFixture('de'));
		$adapter = new Base3IliasLanguage();
		self::assertSame('de', $adapter->getLanguage());
		$adapter->setLanguage('fr');
		self::assertSame('de', $adapter->getLanguage());
	}

	public function testEmptyNativeLanguageUsesEnglishFallback(): void {
		$GLOBALS['DIC'] = new Base3IliasLanguageDicFixture(new Base3IliasLanguageUserFixture(''));
		self::assertSame('en', (new Base3IliasLanguage())->getLanguage());
	}

	public function testMissingNativeUserUsesEnglishWithoutCreatingSessionState(): void {
		$GLOBALS['DIC'] = new Base3IliasLanguageDicFixture(null);
		self::assertSame('en', (new Base3IliasLanguage())->getLanguage());
	}
}
