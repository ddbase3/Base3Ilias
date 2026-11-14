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

use Base3\Base3Ilias\Base3\Base3IliasSystemService;
use Base3\Base3Ilias\Test\Fixture\Base3IliasRegistryFixture;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Fixture/Base3IliasRegistryFixture.php';

final class Base3IliasSystemServiceTest extends TestCase {

	public function testHostAndEmbeddedNamesAndManifestVersion(): void {
		$fixture = new Base3IliasRegistryFixture();
		try {
			$fixture->addModule('Core', ' 4.22.0 ');
			$service = new Base3IliasSystemService($fixture->registry());
			self::assertSame('ILIAS', $service->getHostSystemName());
			self::assertSame('BASE3', $service->getEmbeddedSystemName());
			self::assertSame('4.22.0', $service->getEmbeddedSystemVersion());
		} finally {
			$fixture->remove();
		}
	}

	public function testAbsentFrameworkHasNoInventedVersion(): void {
		$fixture = new Base3IliasRegistryFixture();
		try {
			$service = new Base3IliasSystemService($fixture->registry());
			self::assertSame('', $service->getEmbeddedSystemVersion());
		} finally {
			$fixture->remove();
		}
	}
}
