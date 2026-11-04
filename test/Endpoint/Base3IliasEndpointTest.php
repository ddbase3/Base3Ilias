<?php declare(strict_types=1);

namespace Base3Ilias\Test\Endpoint;

use Base3\Database\Api\IDatabase;
use Base3Ilias\Endpoint\Base3IliasEndpoint;
use PHPUnit\Framework\TestCase;

final class Base3IliasEndpointTest extends TestCase {

	public function testEndpointUsesInjectedDatabaseAndDoesNotCreateItsOwnConnection(): void {
		$db = $this->createMock(IDatabase::class);
		$db->expects(self::once())->method('singleQuery')
			->with('SELECT `obj_id`, `title`, `description` FROM `object_data` WHERE `obj_id` = 1')
			->willReturn(['obj_id' => 1, 'title' => 'Root', 'description' => 'Example']);
		$endpoint = new Base3IliasEndpoint($db);
		self::assertSame('base3iliasendpoint', $endpoint::getName());
		self::assertSame(['obj_id' => 1, 'title' => 'Root', 'description' => 'Example'], json_decode(
			$endpoint->getOutput('json'), true, 512, JSON_THROW_ON_ERROR
		));
		self::assertSame('Base3 ILIAS Endpoint', $endpoint->getHelp());
	}

	public function testMissingRowIsEncodedAsNull(): void {
		$db = $this->createMock(IDatabase::class);
		$db->method('singleQuery')->willReturn(null);
		self::assertSame('null', (new Base3IliasEndpoint($db))->getOutput());
	}
}
