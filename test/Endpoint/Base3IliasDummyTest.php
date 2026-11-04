<?php declare(strict_types=1);

namespace Base3Ilias\Test\Endpoint;

use Base3\Logger\Api\ILogger;
use Base3Ilias\Endpoint\Base3IliasDummy;
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
