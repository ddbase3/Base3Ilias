<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Logger\Api\ILogger;

/**
 * Class Base3IliasLogger
 *
 * Adapter to route Base3 logging calls to the native ILIAS logger ($DIC['ilLog']).
 * Provides PSR-3 compatible methods and backward-compatible Base3 methods.
 */
class Base3IliasLogger implements ILogger {

	private $log;

	public function __construct() {
		global $DIC;
		$this->log = $DIC['ilLog'] ?? null;
	}

	// -----------------------------------------------------
	// PSR-3 Style Logging Methods
	// -----------------------------------------------------

	public function emergency(string|\Stringable $message, array $context = []): void {
		$this->logLevel(self::EMERGENCY, $message, $context);
	}

	public function alert(string|\Stringable $message, array $context = []): void {
		$this->logLevel(self::ALERT, $message, $context);
	}

	public function critical(string|\Stringable $message, array $context = []): void {
		$this->logLevel(self::CRITICAL, $message, $context);
	}

	public function error(string|\Stringable $message, array $context = []): void {
		$this->logLevel(self::ERROR, $message, $context);
	}

	public function warning(string|\Stringable $message, array $context = []): void {
		$this->logLevel(self::WARNING, $message, $context);
	}

	public function notice(string|\Stringable $message, array $context = []): void {
		$this->logLevel(self::NOTICE, $message, $context);
	}

	public function info(string|\Stringable $message, array $context = []): void {
		$this->logLevel(self::INFO, $message, $context);
	}

	public function debug(string|\Stringable $message, array $context = []): void {
		$this->logLevel(self::DEBUG, $message, $context);
	}

	/**
	 * Generic PSR-3 style logging method.
	 *
	 * Writes the message to ILIAS log with a consistent [LEVEL][SCOPE] prefix.
	 */
	public function logLevel(string $level, string|\Stringable $message, array $context = []): void {
		$scope = $context['scope'] ?? 'default';
		$timestamp = $context['timestamp'] ?? time();

		$prefix = '[BASE3][' . strtoupper($level) . '][' . $scope . '] ';
		$line = $prefix . (string) $message;

		// Optionally include interpolated context data
		if (!empty($context)) {
			$contextDump = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if ($contextDump !== '{}') {
				$line .= ' ' . $contextDump;
			}
		}

		if ($this->log) {
			$this->log->write($line);
		} else {
			// Fallback to PHP error log if ilLog unavailable
			error_log($line);
		}
	}

	// -----------------------------------------------------
	// Legacy Project-Specific Logging Methods
	// -----------------------------------------------------

	public function log(string $scope, string $log, ?int $timestamp = null): bool {
		$ts = $timestamp ?? time();
		$msg = '[BASE3][' . $scope . '][' . date('Y-m-d H:i:s', $ts) . '] ' . $log;
		if ($this->log) {
			$this->log->write($msg);
			return true;
		}
		error_log($msg);
		return false;
	}

	public function getScopes(): array {
		// ilLog does not support separate scopes
		return ['ilias'];
	}

	public function getNumOfScopes(): int {
		return 1;
	}

	public function getLogs(string $scope, int $num = 50, bool $reverse = true): array {
		// ILIAS ilLog does not support log retrieval
		return [];
	}
}
