<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasSystemService;
use Base3Ilias\Test\Fixture\Base3IliasRegistryFixture;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Fixture/Base3IliasRegistryFixture.php';

final class Base3IliasSystemServiceTest extends TestCase {

	public function testHostAndEmbeddedNamesAndManifestVersion(): void {
		$fixture = new Base3IliasRegistryFixture();
		try {
			$fixture->addModule('Base3Framework', ' 4.22.0 ');
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
