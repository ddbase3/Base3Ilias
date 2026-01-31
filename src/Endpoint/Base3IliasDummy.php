<?php declare(strict_types=1);

namespace Base3Ilias\Endpoint;

use Base3\Api\IOutput;
use Base3\Logger\Api\ILogger;

class Base3IliasDummy implements IOutput {

	public function __construct(private readonly ILogger $logger) {}

	// Implementation of IBase

	public static function getName(): string {
		return 'base3iliasdummy';
	}

	// Implementation of IOutput

	public function getOutput(string $out = 'html', bool $final = false): string {

		$this->logger->log('Base3IliasDummy', 'Das ist ein Test-Log');

		$row = [
			"id" => 1,
			"name" => "dummy",
			"description" => "This is a test endpoint"
		];
		return json_encode($row);
	}

	public function getHelp(): string {
		return 'Base3 ILIAS Dummy Endpoint';
	}
}
