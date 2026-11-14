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
use Base3\CoreFoundation\Database\Api\IDatabase;

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
