<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Database\Api\IDatabase;
use ilDBInterface;

/**
 * Base3IliasDatabase
 *
 * Adapter that maps the BASE3 IDatabase interface to the ILIAS database service (ilDBInterface).
 *
 * Notes / limitations:
 * - ILIAS DB is managed by the global DIC; "connect" / "disconnect" only attach/detach this adapter
 *   from the shared DB service, they do not open/close the physical database connection directly.
 * - affectedRows() and insertId() are not reliably available via ilDBInterface in a backend-agnostic way,
 *   so these methods return safe defaults.
 * - Error handling: ILIAS typically throws exceptions on failures; this adapter captures the last exception
 *   and exposes it via isError()/errorNumber()/errorMessage().
 */
class Base3IliasDatabase implements IDatabase {

	private ?ilDBInterface $db = null;
	private bool $connected = false;
	private bool $hasError = false;
	private int $lastErrorNumber = 0;
	private string $lastErrorMessage = '';

	public function __construct() {
		$this->connect();
	}

	public function connect(): void {
		if ($this->connected) return;

		$this->resetErrorState();

		global $DIC;

		if (!isset($DIC) || $DIC === null || $DIC->database() === null) {
			$this->db = null;
			$this->connected = false;
			return;
		}

		$this->db = $DIC->database();
		$this->connected = true;
	}

	public function connected(): bool {
		return $this->connected && $this->db !== null;
	}

	public function disconnect(): void {
		$this->db = null;
		$this->connected = false;
	}

	public function beginTransaction(): void {
		$this->connect();
		$this->requireDb();

		try {
			$this->db->beginTransaction();
			$this->resetErrorState();
		} catch (\Throwable $e) {
			$this->storeError($e);
			throw new \RuntimeException('Failed to begin transaction: ' . $e->getMessage(), 0, $e);
		}
	}

	public function commit(): void {
		$this->connect();
		$this->requireDb();

		try {
			$this->db->commit();
			$this->resetErrorState();
		} catch (\Throwable $e) {
			$this->storeError($e);
			throw new \RuntimeException('Failed to commit transaction: ' . $e->getMessage(), 0, $e);
		}
	}

	public function rollback(): void {
		$this->connect();
		$this->requireDb();

		try {
			$this->db->rollback();
			$this->resetErrorState();
		} catch (\Throwable $e) {
			$this->storeError($e);
			throw new \RuntimeException('Failed to rollback transaction: ' . $e->getMessage(), 0, $e);
		}
	}

	public function nonQuery(string $query): void {
		$this->connect();
		$this->requireDb();

		try {
			$this->db->manipulate($query);
			$this->resetErrorState();
		} catch (\Throwable $e) {
			$this->storeError($e);
			throw new \RuntimeException('Failed to execute nonQuery: ' . $e->getMessage(), 0, $e);
		}
	}

	public function scalarQuery(string $query): mixed {
		$this->connect();
		$this->requireDb();

		try {
			$stmt = $this->db->query($query);
			$row = $this->db->fetchAssoc($stmt);

			if (is_object($stmt) || is_resource($stmt)) {
				$this->db->free($stmt);
			}

			$this->resetErrorState();

			if (!$row) return null;

			$values = array_values($row);
			return $values[0] ?? null;
		} catch (\Throwable $e) {
			$this->storeError($e);
			throw new \RuntimeException('Failed to execute scalarQuery: ' . $e->getMessage(), 0, $e);
		}
	}

	public function singleQuery(string $query): ?array {
		$this->connect();
		$this->requireDb();

		try {
			$stmt = $this->db->query($query);
			$row = $this->db->fetchAssoc($stmt);

			if (is_object($stmt) || is_resource($stmt)) {
				$this->db->free($stmt);
			}

			$this->resetErrorState();
			return $row ?: null;
		} catch (\Throwable $e) {
			$this->storeError($e);
			throw new \RuntimeException('Failed to execute singleQuery: ' . $e->getMessage(), 0, $e);
		}
	}

	public function &listQuery(string $query): array {
		$this->connect();
		$this->requireDb();

		$list = [];

		try {
			$stmt = $this->db->query($query);

			while ($row = $this->db->fetchAssoc($stmt)) {
				$values = array_values($row);
				$list[] = $values[0] ?? null;
			}

			if (is_object($stmt) || is_resource($stmt)) {
				$this->db->free($stmt);
			}

			$this->resetErrorState();
		} catch (\Throwable $e) {
			$this->storeError($e);
			throw new \RuntimeException('Failed to execute listQuery: ' . $e->getMessage(), 0, $e);
		}

		return $list;
	}

	public function &multiQuery(string $query): array {
		$this->connect();
		$this->requireDb();

		$rows = [];

		try {
			$stmt = $this->db->query($query);

			while ($row = $this->db->fetchAssoc($stmt)) {
				$rows[] = $row;
			}

			if (is_object($stmt) || is_resource($stmt)) {
				$this->db->free($stmt);
			}

			$this->resetErrorState();
		} catch (\Throwable $e) {
			$this->storeError($e);
			throw new \RuntimeException('Failed to execute multiQuery: ' . $e->getMessage(), 0, $e);
		}

		return $rows;
	}

	public function affectedRows(): int {
		return 0;
	}

	public function insertId(): int|string {
		return 0;
	}

	public function escape(string $str): string {
		$this->connect();
		$this->requireDb();

		try {
			if (method_exists($this->db, 'quote')) {
				$quoted = $this->db->quote($str, 'text');
				$this->resetErrorState();
				return substr($quoted, 1, -1);
			}

			$str = str_replace(
				["\\", "\x00", "\n", "\r", "'", '"', "\x1a"],
				["\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"],
				$str
			);

			$this->resetErrorState();
			return $str;
		} catch (\Throwable $e) {
			$this->storeError($e);
			throw new \RuntimeException('Failed to escape string: ' . $e->getMessage(), 0, $e);
		}
	}

	public function isError(): bool {
		return $this->hasError;
	}

	public function errorNumber(): int {
		return $this->lastErrorNumber;
	}

	public function errorMessage(): string {
		return $this->lastErrorMessage;
	}

	private function requireDb(): void {
		if ($this->db === null) {
			throw new \RuntimeException('ILIAS database service is not available.');
		}
	}

	private function resetErrorState(): void {
		$this->hasError = false;
		$this->lastErrorNumber = 0;
		$this->lastErrorMessage = '';
	}

	private function storeError(\Throwable $e): void {
		$this->hasError = true;
		$this->lastErrorNumber = $e->getCode() > 0 ? (int) $e->getCode() : 0;
		$this->lastErrorMessage = $e->getMessage();
	}
}
