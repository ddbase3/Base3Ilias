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

use Base3\CoreFoundation\Language\Api\ILanguage;
use Base3\Base3Ilias\Base3\Base3IliasMvcView;
use Base3\Base3Ilias\Test\Fixture\Base3IliasRegistryFixture;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Fixture/Base3IliasRegistryFixture.php';

final class Base3IliasMvcViewTest extends TestCase {

	public function testDirectModulePathRendersTheRealTemplate(): void {
		$fixture = new Base3IliasRegistryFixture();
		try {
			$modulePath = $fixture->addModule('Demo');
			$fixture->write('Demo', 'tpl/hello.php', '<?php echo "hello from module";');
			$language = $this->createMock(ILanguage::class);
			$view = new Base3IliasMvcView($language, $fixture->registry());
			$view->setPath($modulePath);
			$view->setTemplate('hello.php');
			self::assertSame('hello from module', $view->loadTemplate());
		} finally {
			$fixture->remove();
		}
	}

	public function testLegacyBase3PluginPathIsMappedToDiscoveredModule(): void {
		$fixture = new Base3IliasRegistryFixture();
		try {
			$fixture->addModule('Demo');
			$fixture->write('Demo', 'tpl/hello.php', '<?php echo "resolved module";');
			$language = $this->createMock(ILanguage::class);
			$view = new Base3IliasMvcView($language, $fixture->registry());
			if (defined('DIR_PLUGIN')) {
				$view->setPath(rtrim(DIR_PLUGIN, '/\\') . '/Demo');
			} else {
				$view->setPath($fixture->root . '/Demo');
			}
			$view->setTemplate('hello.php');
			self::assertSame('resolved module', $view->loadTemplate());
		} finally {
			$fixture->remove();
		}
	}
}
