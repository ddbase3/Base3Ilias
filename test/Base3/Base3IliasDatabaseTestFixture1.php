<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasDatabase;
use ilDBPdoInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Base3IliasDatabaseTestFixture1 {

			public function __construct(private readonly ilDBPdoInterface $database) {}

			public function database(): ilDBPdoInterface {
				return $this->database;
			}
		}
