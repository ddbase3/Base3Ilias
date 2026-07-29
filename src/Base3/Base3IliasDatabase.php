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
 * - ILIAS manages the database connection lifecycle via DIC; connect() validates availability and
 *   disconnect() is a no-op.
 * - affectedRows() contains the result of the latest nonQuery() call.
 * - insertId() uses the MySQL/MariaDB connection-local LAST_INSERT_ID() value. Before every nonQuery()
 *   this value is reset to 0, so non-insert statements cannot expose a stale ID.
 * - Database exceptions are recorded for isError()/errorNumber()/errorMessage() and rethrown unchanged.
 * - Transaction return values are checked; failed transaction operations are exposed as exceptions.
 */
class Base3IliasDatabase implements IDatabase {

	/**
	 * @var ilDBInterface|null
	 */
	private ?ilDBInterface $db = null;

	private int $lastAffectedRows = 0;
	private int|string $lastInsertId = 0;
	private ?\Throwable $lastError = null;

	public function __construct() {
		global $DIC;

		if($DIC !== null && $DIC->database() !== null) {
			$this->db = $DIC->database();
		}
	}

	public function connect(): void {
		$this->clearError();

		try {
			$this->requireDb();
		} catch(\Throwable $error) {
			$this->rememberError($error);
			throw $error;
		}
	}

	public function connected(): bool {
		return $this->db !== null;
	}

	public function disconnect(): void {
		// ILIAS manages the DB connection lifecycle via DIC.
		// Closing the shared connection here would affect the complete request.
	}

	public function beginTransaction(): void {
		$this->clearError();

		try {
			$this->requireDb();

			if(!$this->db->beginTransaction()) {
				throw new \RuntimeException('ILIAS failed to begin the database transaction.');
			}
		} catch(\Throwable $error) {
			$this->rememberError($error);
			throw $error;
		}
	}

	public function commit(): void {
		$this->clearError();

		try {
			$this->requireDb();

			if(!$this->db->commit()) {
				throw new \RuntimeException('ILIAS failed to commit the database transaction.');
			}
		} catch(\Throwable $error) {
			$this->rememberError($error);
			throw $error;
		}
	}

	public function rollback(): void {
		$this->clearError();

		try {
			$this->requireDb();

			if(!$this->db->rollback()) {
				throw new \RuntimeException('ILIAS failed to roll back the database transaction.');
			}
		} catch(\Throwable $error) {
			$this->rememberError($error);
			throw $error;
		}
	}

	public function nonQuery(string $query): void {
		$this->resetStatementState();

		try {
			$this->requireDb();
			$this->resetConnectionInsertId();

			$this->lastAffectedRows = $this->db->manipulate($query);
			$this->lastInsertId = $this->readConnectionInsertId();
		} catch(\Throwable $error) {
			$this->rememberError($error);
			throw $error;
		}
	}

	public function scalarQuery(string $query): mixed {
		$this->resetStatementState();
		$stmt = null;
		$value = null;

		try {
			$this->requireDb();

			$stmt = $this->db->query($query);
			$row = $this->db->fetchAssoc($stmt);

			if($row) {
				$values = array_values($row);
				$value = $values[0] ?? null;
			}
		} catch(\Throwable $error) {
			$this->rememberError($error);
			throw $error;
		} finally {
			if($stmt !== null && $this->db !== null) {
				$this->db->free($stmt);
			}
		}

		return $value;
	}

	public function singleQuery(string $query): ?array {
		$this->resetStatementState();
		$stmt = null;
		$row = null;

		try {
			$this->requireDb();

			$stmt = $this->db->query($query);
			$row = $this->db->fetchAssoc($stmt);
		} catch(\Throwable $error) {
			$this->rememberError($error);
			throw $error;
		} finally {
			if($stmt !== null && $this->db !== null) {
				$this->db->free($stmt);
			}
		}

		return $row ?: null;
	}

	public function &listQuery(string $query): array {
		$this->resetStatementState();
		$stmt = null;
		$list = [];

		try {
			$this->requireDb();

			$stmt = $this->db->query($query);

			while($row = $this->db->fetchAssoc($stmt)) {
				$values = array_values($row);
				$list[] = $values[0] ?? null;
			}
		} catch(\Throwable $error) {
			$this->rememberError($error);
			throw $error;
		} finally {
			if($stmt !== null && $this->db !== null) {
				$this->db->free($stmt);
			}
		}

		return $list;
	}

	public function &multiQuery(string $query): array {
		$this->resetStatementState();
		$stmt = null;
		$rows = [];

		try {
			$this->requireDb();

			$stmt = $this->db->query($query);

			while($row = $this->db->fetchAssoc($stmt)) {
				$rows[] = $row;
			}
		} catch(\Throwable $error) {
			$this->rememberError($error);
			throw $error;
		} finally {
			if($stmt !== null && $this->db !== null) {
				$this->db->free($stmt);
			}
		}

		return $rows;
	}

	public function affectedRows(): int {
		return $this->lastAffectedRows;
	}

	public function insertId(): int|string {
		return $this->lastInsertId;
	}

	public function escape(string $str): string {
		// Escapes to a quoted-safe fragment; callers still add surrounding quotes themselves.
		$str = str_replace(
			["\\", "\x00", "\n", "\r", "'", '"', "\x1a"],
			["\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"],
			$str
		);

		return $str;
	}

	public function isError(): bool {
		return $this->lastError !== null;
	}

	public function errorNumber(): int {
		return $this->lastError !== null ? (int)$this->lastError->getCode() : 0;
	}

	public function errorMessage(): string {
		return $this->lastError?->getMessage() ?? '';
	}

	private function resetConnectionInsertId(): void {
		$stmt = $this->db->query('SELECT LAST_INSERT_ID(0) AS insert_id');
		$this->db->free($stmt);
	}

	private function readConnectionInsertId(): int|string {
		$stmt = $this->db->query('SELECT LAST_INSERT_ID() AS insert_id');

		try {
			$row = $this->db->fetchAssoc($stmt);
		} finally {
			$this->db->free($stmt);
		}

		$value = $row['insert_id'] ?? 0;

		if(is_int($value) || is_string($value)) {
			return $value;
		}

		return (int)$value;
	}

	private function resetStatementState(): void {
		$this->lastAffectedRows = 0;
		$this->lastInsertId = 0;
		$this->lastError = null;
	}

	private function clearError(): void {
		$this->lastError = null;
	}

	private function rememberError(\Throwable $error): void {
		$this->lastError = $error;
	}

	private function requireDb(): void {
		if($this->db === null) {
			throw new \RuntimeException('ILIAS database service is not available (DIC/database is null).');
		}
	}
}
