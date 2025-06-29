<?php declare(strict_types=1);

namespace Base3Ilias;

use Base3\Logger\Api\ILogger;

class Base3IliasLogger implements ILogger {

        private $log;

        public function __construct() {
                global $DIC;
		$this->log = $DIC['ilLog'];
        }

	// Implementation of ILogger

	public function log(string $scope, string $log, ?int $timestamp = null): bool {
		$msg = '[BASE3] [' . $scope . '] ' . $log;
		$this->log->write($msg);
		return true;
	}

	public function getScopes(): array {
		return [];
	}

	public function getNumOfScopes() {
		return 0;
	}

	public function getLogs(string $scope, int $num = 50, bool $reverse = true): array {
		return [];
	}
}

