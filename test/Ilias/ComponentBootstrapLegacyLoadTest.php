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


namespace Base3\Base3Ilias\Test\Ilias;

use PHPUnit\Framework\TestCase;

final class ComponentBootstrapLegacyLoadTest extends TestCase {

	public function testClassDiscoveryDoesNotRequireNativeIliasComponentInterfaces(): void {
		$file = realpath(__DIR__ . '/../../src/Ilias/ComponentBootstrap.php');
		self::assertNotFalse($file);

		$script = 'require_once ' . var_export($file, true) . '; '
			. 'if (!class_exists(\\Base3\\Base3Ilias\\Ilias\\ComponentBootstrap::class, false)) exit(2); '
			. 'if (class_exists(\\Base3\\Base3Ilias\\Base3IliasPublicAsset::class, false)) exit(3); '
			. 'if (interface_exists(\\ILIAS\\Component\\Resource\\PublicAsset::class, false)) exit(4);';

		$command = escapeshellarg(PHP_BINARY)
			. ' -d display_errors=stderr -r '
			. escapeshellarg($script) . ' 2>&1';

		exec($command, $lines, $status);
		self::assertSame(0, $status, implode("\n", $lines));
	}

	public function testNativeAssetContributionArrayIsPassedByReference(): void {
		$file = realpath(__DIR__ . '/../../src/Ilias/ComponentBootstrap.php');
		self::assertNotFalse($file);
		require_once $file;

		$method = new \ReflectionMethod(\Base3\Base3Ilias\Ilias\ComponentBootstrap::class, 'deployAssets');
		self::assertTrue($method->getParameters()[0]->isPassedByReference());
	}
}
