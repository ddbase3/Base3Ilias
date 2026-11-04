<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasLanguage;
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
