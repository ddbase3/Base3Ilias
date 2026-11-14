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


namespace Base3\Base3Ilias\Endpoint;

use Base3\CoreFoundation\Api\IOutput;
use Base3\CoreFoundation\Logger\Api\ILogger;

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
