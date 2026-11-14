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


namespace Base3\Base3Ilias\Test\Endpoint;

use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\Base3Ilias\Endpoint\Base3IliasDummy;
use PHPUnit\Framework\TestCase;

final class Base3IliasDummyTest extends TestCase {

	public function testEndpointProvidesItsIdentifierAndLogsExactlyOnce(): void {
		$logger = $this->createMock(ILogger::class);
		$logger->expects(self::once())->method('log')->with('Base3IliasDummy', 'Das ist ein Test-Log');
		$endpoint = new Base3IliasDummy($logger);
		self::assertSame('base3iliasdummy', $endpoint::getName());
		self::assertSame([
			'id' => 1, 'name' => 'dummy', 'description' => 'This is a test endpoint'
		], json_decode($endpoint->getOutput(), true, 512, JSON_THROW_ON_ERROR));
		self::assertSame('Base3 ILIAS Dummy Endpoint', $endpoint->getHelp());
	}
}
