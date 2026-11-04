<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Language\Api\ILanguage;
use Base3Ilias\Base3\Base3IliasTranslation;
use Base3Ilias\Test\Fixture\Base3IliasRegistryFixture;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Fixture/Base3IliasRegistryFixture.php';

final class Base3IliasTranslationTest extends TestCase {

	private Base3IliasRegistryFixture $fixture;

	protected function setUp(): void {
		parent::setUp();
		$this->fixture = new Base3IliasRegistryFixture();
		$this->fixture->addModule('Base3Ilias');
		$this->fixture->addModule('Extension');
	}

	protected function tearDown(): void {
		$this->fixture->remove();
		parent::tearDown();
	}

	public function testPluginTranslationOverridesBaseWithEnglishFallback(): void {
		$this->fixture->write('Base3Ilias', 'lang/Demo/en.ini', "[sample]\nhello = Base\nempty = Base\n");
		$this->fixture->write('Extension', 'lang/Demo/en.ini', "[sample]\nhello = Override\nempty = \n");
		$this->fixture->write('Base3Ilias', 'lang/Demo/de.ini', "[sample]\nhello = Hallo\n");
		$translation = $this->translation('fr');
		self::assertSame('Override', $translation->translate('Demo', 'sample', 'hello'));
		self::assertSame('empty', $translation->translate('Demo', 'sample', 'empty'));
		self::assertSame('Hallo', $this->translation('de')->translate('Demo', 'sample', 'hello'));
	}

	public function testMissingAndBlankTranslationsUseCallerFallbackAndReplacements(): void {
		$this->fixture->write('Base3Ilias', 'lang/Demo/en.ini', "[sample]\nblank = \ntext = Hello {person}!\n");
		$translation = $this->translation('en');
		self::assertSame('Welcome Ada', $translation->translate('Demo', 'sample', 'unknown', 'Welcome {name}', ['name' => 'Ada']));
		self::assertSame('blank', $translation->translate('Demo', 'sample', 'blank'));
		self::assertSame('Hello Ada!', $translation->translate('Demo', 'sample', 'text', '', ['person' => 'Ada']));
	}

	public function testLoadedCatalogIsCachedWithinTranslationInstance(): void {
		$file = $this->fixture->write('Base3Ilias', 'lang/Demo/en.ini', "[sample]\nhello = Initial\n");
		$translation = $this->translation('');
		self::assertSame('Initial', $translation->translate('Demo', 'sample', 'hello'));
		file_put_contents($file, "[sample]\nhello = Updated\n");
		self::assertSame('Initial', $translation->translate('Demo', 'sample', 'hello'));
		self::assertSame('Updated', $this->translation('en')->translate('Demo', 'sample', 'hello'));
	}


	public function testBlankLocalizedValuesFallBackToEnglishWithoutLosingOtherLocalizedKeys(): void {
		$this->fixture->write('Base3Ilias', 'lang/Interface/de.ini', "[buttons]\napply = Anwenden\ncancel = \n");
		$this->fixture->write('Base3Ilias', 'lang/Interface/en.ini', "[buttons]\napply = Apply\ncancel = Cancel\n");
		$translation = $this->translation(' de ');
		self::assertSame('Anwenden', $translation->translate('Interface', 'buttons', 'apply'));
		self::assertSame('Cancel', $translation->translate('Interface', 'buttons', 'cancel'));
	}

	public function testMissingLanguageAndMissingCatalogUseEnglishThenCallerFallback(): void {
		$this->fixture->write('Base3Ilias', 'lang/Interface/en.ini', "[buttons]\nsubmit = Submit\n");
		$translation = $this->translation('es');
		self::assertSame('Submit', $translation->translate('Interface', 'buttons', 'submit'));
		self::assertSame('Unknown', $translation->translate('Interface', 'missing', 'unknown', 'Unknown'));
		self::assertSame('undefined_key', $translation->translate('Missing', 'section', 'undefined_key'));
	}

	public function testLaterModuleOverridesOnlyMatchingKeysWithoutRemovingOtherSections(): void {
		$this->fixture->addModule('Second');
		$this->fixture->write('Base3Ilias', 'lang/Shared/en.ini', "[first]\nkey = Base\nremain = Original\n[second]\nother = Base section\n");
		$this->fixture->write('Extension', 'lang/Shared/en.ini', "[first]\nkey = Extension\n");
		$this->fixture->write('Second', 'lang/Shared/en.ini', "[first]\nkey = Last module\n");
		$translation = $this->translation('en');
		self::assertSame('Last module', $translation->translate('Shared', 'first', 'key'));
		self::assertSame('Original', $translation->translate('Shared', 'first', 'remain'));
		self::assertSame('Base section', $translation->translate('Shared', 'second', 'other'));
	}

	public function testReplacementsAcceptPlainAndBracedTokensAndScalarValues(): void {
		$this->fixture->write('Base3Ilias', 'lang/Shared/en.ini', "[message]\ntemplate = Hello {who}, who: {who}, amount={count}, false: {flag}, null: {empty}\n");
		$translation = $this->translation('en');
		self::assertSame(
			'Hello Ada, Ada: Ada, amount=0, false: , null: ',
			$translation->translate('Shared', 'message', 'template', '', [
				'who' => 'Ada', 'count' => 0, 'flag' => false, 'empty' => null
			])
		);
		self::assertSame('Hi Ada', $translation->translate('NoCatalog', 'none', 'unavailable', 'Hi {who}', ['who' => 'Ada']));
	}

	public function testBaseModuleIsSearchedEvenWhenOtherModulesHaveNoLanguageDirectory(): void {
		$this->fixture->addModule('WithoutLang');
		$this->fixture->write('Base3Ilias', 'lang/TestSet/en.ini', "[test]\nvalue = From base\n");
		self::assertSame('From base', $this->translation('')->translate('TestSet', 'test', 'value'));
	}

	public function testLoadedMissingCatalogIsCachedUntilNewTranslationIsConstructed(): void {
		$translation = $this->translation('en');
		self::assertSame('unknown', $translation->translate('Late', 'section', 'unknown'));
		$this->fixture->write('Extension', 'lang/Late/en.ini', "[section]\nunknown = Now available\n");
		self::assertSame('unknown', $translation->translate('Late', 'section', 'unknown'));
		self::assertSame('Now available', $this->translation('en')->translate('Late', 'section', 'unknown'));
	}


	private function translation(string $language): Base3IliasTranslation {
		$provider = $this->createMock(ILanguage::class);
		$provider->method('getLanguage')->willReturn($language);
		return new Base3IliasTranslation($provider, $this->fixture->registry());
	}
}
