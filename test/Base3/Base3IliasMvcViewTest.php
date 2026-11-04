<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Language\Api\ILanguage;
use Base3Ilias\Base3\Base3IliasMvcView;
use Base3Ilias\Test\Fixture\Base3IliasRegistryFixture;
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
