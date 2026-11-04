<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasLanguage;
use PHPUnit\Framework\TestCase;

final class Base3IliasLanguageTest extends TestCase {

	private bool $hadDic;
	private mixed $originalDic;

	protected function setUp(): void {
		parent::setUp();
		$this->hadDic = array_key_exists('DIC', $GLOBALS);
		$this->originalDic = $GLOBALS['DIC'] ?? null;
		$GLOBALS['DIC'] = null;
	}

	protected function tearDown(): void {
		if ($this->hadDic) $GLOBALS['DIC'] = $this->originalDic;
		else unset($GLOBALS['DIC']);
		parent::tearDown();
	}

	public function testNoAuthenticatedUserUsesEnglishWithoutChangingIliasState(): void {
		$language = new Base3IliasLanguage();
		self::assertSame('en', $language->getLanguage());
		$language->setLanguage('fr');
		self::assertSame('en', $language->getLanguage());
	}
}
