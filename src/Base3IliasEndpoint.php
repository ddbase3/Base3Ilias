<?php declare(strict_types=1);

namespace Base3Ilias;

use Base3\Api\IOutput;
use Base3\Database\Api\IDatabase;

class Base3IliasEndpoint implements IOutput {

	public function __construct(
		private readonly IDatabase $database
	) {}

	// Implementation of IBase

	public function getName() {
		return 'base3iliasendpoint';
	}

	// Implementation of IOutput

	public function getOutput($out = "html") {
        $sql = 'SELECT `obj_id`, `title`, `description` FROM `object_data` WHERE `obj_id` = 1';
		$row = $this->database->singleQuery($sql);
		return json_encode($row);
	}

	public function getHelp() {
		return 'Base3 ILIAS Endpoint';
	}
}
