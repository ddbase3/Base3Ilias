<?php declare(strict_types=1);

namespace Base3Ilias;

use Base3\Api\IOutput;

class Base3IliasDummy implements IOutput {

	// Implementation of IBase

	public function getName() {
		return 'base3iliasdummy';
	}

	// Implementation of IOutput

	public function getOutput($out = "html") {
		$row = [
			"id" => 1,
			"name" => "dummy",
			"description" => "This is a test endpoint"
		];
		return json_encode($row);
	}

	public function getHelp() {
		return 'Base3 ILIAS Dummy Endpoint';
	}
}
