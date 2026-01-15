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
 * - ILIAS DB is managed by the global DIC; "connect" / "disconnect" are effectively no-ops here.
 * - affectedRows() and insertId() are not reliably available via ilDBInterface in a backend-agnostic way,
 *   so these methods return safe defaults.
 * - Error handling: ILIAS typically throws exceptions on failures; therefore isError()/errorNumber()/errorMessage()
 *   return default values.
 */
class Base3IliasDatabase implements IDatabase {

	/**
	 * @var ilDBInterface|null
	 */
	private ?ilDBInterface $db = null;

	public function __construct() {
		global $DIC;

		if ($DIC !== null && $DIC->database() !== null) {
			$this->db = $DIC->database();
		}
	}

	public function connect(): void {
		// ILIAS manages the DB connection lifecycle via DIC.
		// Keeping this method as a no-op satisfies the lazy-connect contract.
	}

	public function connected(): bool {
		global $DIC;
		return $DIC !== null && $DIC->database() !== null;
	}

	public function disconnect(): void {
		// ILIAS manages the DB connection lifecycle via DIC.
		// There is no reliable way to close it here without side effects.
	}

	public function nonQuery(string $query): void {
		$this->requireDb();
		$this->db->manipulate($query);
	}

	public function scalarQuery(string $query): mixed {
		$this->requireDb();

		$stmt = $this->db->query($query);
		$row = $this->db->fetchAssoc($stmt);

		// If no row found, return null (per interface contract).
		if (!$row) {
			return null;
		}

		$values = array_values($row);
		return $values[0] ?? null;
	}

	public function singleQuery(string $query): ?array {
		$this->requireDb();

		$stmt = $this->db->query($query);
		$row = $this->db->fetchAssoc($stmt);

		// Must return null if no row found.
		return $row ?: null;
	}

	public function &listQuery(string $query): array {
		$this->requireDb();

		$stmt = $this->db->query($query);
		$list = [];

		while ($row = $this->db->fetchAssoc($stmt)) {
			$values = array_values($row);
			$list[] = $values[0] ?? null;
		}

		$this->db->free($stmt);
		return $list;
	}

	public function &multiQuery(string $query): array {
		$this->requireDb();

		$stmt = $this->db->query($query);
		$rows = [];

		while ($row = $this->db->fetchAssoc($stmt)) {
			$rows[] = $row;
		}

		$this->db->free($stmt);
		return $rows;
	}

	public function affectedRows(): int {
		// ilDBInterface does not expose a consistent affected-rows method across drivers.
		// Returning 0 is a safe default (caller must not rely on it for ILIAS adapter).
		return 0;
	}

	public function insertId(): int|string {
		// ilDBInterface does not provide a consistent "last insert id" accessor across drivers.
		// Returning 0 is a safe default (caller must not rely on it for ILIAS adapter).
		return 0;
	}

	public function escape(string $str): string {
		// Escapes to a quoted-safe fragment; callers still add surrounding quotes themselves.
		$str = str_replace(
			["\\",   "\x00", "\n",  "\r",  "'",   '"',  "\x1a"],
			["\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"],
			$str
		);

		return $str;
	}

	public function isError(): bool {
		// ILIAS DB layer usually throws exceptions on error, so there is no persistent error flag.
		return false;
	}

	public function errorNumber(): int {
		// Not available via ilDBInterface in a portable way.
		return 0;
	}

	public function errorMessage(): string {
		// Not available via ilDBInterface in a portable way.
		return '';
	}

	private function requireDb(): void {
		if ($this->db === null) {
			throw new \RuntimeException('ILIAS database service is not available (DIC/database is null).');
		}
	}
}
