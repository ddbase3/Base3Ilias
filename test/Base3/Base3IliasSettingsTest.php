<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Translation\Api\ITranslation;
use Base3Ilias\Base3\Base3IliasSettings;
use PHPUnit\Framework\TestCase;

final class Base3IliasSettingsTest extends TestCase {

	public function testAdministrationCatalogUsesOwningTranslationServiceForEveryDisplay(): void {
		$translation = $this->createMock(ITranslation::class);
		$keys = [];
		$translation->method('translate')->willReturnCallback(
			static function(string $set, string $section, string $key, string $fallback, array $replacements = []) use (&$keys): string {
				self::assertSame('Administration', $set);
				self::assertSame('administration', $section);
				self::assertSame([], $replacements);
				self::assertNotSame('', $fallback);
				$keys[] = $key;
				return 'translated:' . $key;
			}
		);
		$tabs = (new Base3IliasSettings($translation))->getAdministrationConfig();
		self::assertCount(1, $tabs);
		self::assertSame('system', $tabs[0]['name']);
		self::assertSame('translated:base3_admin_tab_system', $tabs[0]['label']);
		$displays = $tabs[0]['displays'];
		self::assertGreaterThan(10, count($displays));
		self::assertContains('iliasdashboarddisplay', array_column($displays, 'name'));
		self::assertContains('databaseworkbenchdisplay', array_column($displays, 'name'));
		self::assertCount(count($displays) + 1, $keys);
		foreach ($displays as $display) {
			self::assertSame('translated:base3_admin_subtab_' . $display['name'], $display['label']);
		}
	}
}
