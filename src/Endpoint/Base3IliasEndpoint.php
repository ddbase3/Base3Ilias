<?php declare(strict_types=1);

namespace Base3Ilias\Endpoint;

use Base3\Api\IOutput;
use Base3\Database\Api\IDatabase;

class Base3IliasEndpoint implements IOutput {

	public function __construct(
		private readonly IDatabase $database
	) {}

	// Implementation of IBase

	public static function getName(): string {
		return 'base3iliasendpoint';
	}

	// Implementation of IOutput

	public function getOutput(string $out = 'html', bool $final = false): string {
		$sql = 'SELECT `obj_id`, `title`, `description` FROM `object_data` WHERE `obj_id` = 1';
		$row = $this->database->singleQuery($sql);
		return json_encode($row);
	}

	public function getHelp(): string {
		return 'Base3 ILIAS Endpoint';
	}
}
