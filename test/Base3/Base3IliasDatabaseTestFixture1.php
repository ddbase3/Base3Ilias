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

use Base3\Base3Ilias\Base3\Base3IliasDatabase;
use ilDBPdoInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Base3IliasDatabaseTestFixture1 {

			public function __construct(private readonly ilDBPdoInterface $database) {}

			public function database(): ilDBPdoInterface {
				return $this->database;
			}
		}
