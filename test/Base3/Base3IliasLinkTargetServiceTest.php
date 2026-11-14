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

use Base3\CoreFoundation\Configuration\Api\IConfiguration;
use Base3\Base3Ilias\Base3\Base3IliasLinkTargetService;
use PHPUnit\Framework\TestCase;

final class Base3IliasLinkTargetServiceTest extends TestCase {

	public function testDefaultEndpointAndOutputArePreserved(): void {
		$configuration = $this->createMock(IConfiguration::class);
		$configuration->method('get')->with('base')->willReturn([]);
		$link = (new Base3IliasLinkTargetService($configuration))->getLink(['name' => 'chat'], ['value' => 'a b']);
		self::assertStringStartsWith('index.php?', $link);
		parse_str((string) parse_url($link, PHP_URL_QUERY), $values);
		self::assertSame('ilUIPluginRouterGUI', $values['baseClass']);
		self::assertSame('ilBase3IliasAdapterAjaxGUI', $values['cmdClass']);
		self::assertSame('dispatch', $values['cmd']);
		self::assertSame('chat', $values['name']);
		self::assertSame('php', $values['out']);
		self::assertSame('a b', $values['value']);
	}

	public function testCustomEndpointAndQueryParamsWithCaching(): void {
		$configuration = $this->createMock(IConfiguration::class);
		$configuration->expects(self::once())->method('get')->with('base')->willReturn(['endpoint' => '/base/dispatch']);
		$service = new Base3IliasLinkTargetService($configuration);
		self::assertSame('/base/dispatch?name=item&out=json&tag%5B0%5D=one', $service->getLink(
			['name' => 'item', 'out' => 'json'], ['tag' => ['one']]
		));
		self::assertSame('/base/dispatch?name=next&out=php', $service->getLink(['name' => 'next']));
	}

	public function testEndpointWithExistingQueryUsesAmpersandAndPreservesParameterOverride(): void {
		$configuration = $this->createMock(IConfiguration::class);
		$configuration->method('get')->willReturn(['endpoint' => '/index.php?cmd=dispatch']);
		self::assertSame(
			'/index.php?cmd=dispatch&name=replaced&out=json',
			(new Base3IliasLinkTargetService($configuration))->getLink(['name' => 'initial'], ['name' => 'replaced', 'out' => 'json'])
		);
	}
}
