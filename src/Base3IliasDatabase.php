<?php declare(strict_types=1);

namespace Base3Ilias;

use Base3\Database\Api\IDatabase;

class Base3IliasDatabase implements IDatabase {

	private $db;

	public function __construct() {
		global $DIC;
		if ($DIC != null) $this->db = $DIC->database();
	}

	public function connect() {
	}

	public function connected() {
		global $DIC;
		return $DIC != null && $DIC->database() != null;
	}

	public function disconnect() {
	}

	public function nonQuery($query) {
		$this->db->manipulate($query);
	}

	public function scalarQuery($query) {
		$stmt = $this->db->query($query);
		$row = $this->db->fetchAssoc($stmt);
		return $row ? array_values($row)[0] : null;
	}

	public function singleQuery($query) {
		$stmt = $this->db->query($query);
		return $this->db->fetchAssoc($stmt);
	}

	public function &listQuery($query) {
		$stmt = $this->db->query($query);
		$list = [];
		while ($row = $this->db->fetchAssoc($stmt)) {
			$list[] = array_values($row)[0];
		}
		$this->db->free($stmt);
		return $list;
	}

	public function &multiQuery($query) {
		$stmt = $this->db->query($query);
		$rows = [];
		while ($row = $this->db->fetchAssoc($stmt)) {
			$rows[] = $row;
		}
		$this->db->free($stmt);
		return $rows;
	}

	public function affectedRows() {
		// Wird direkt zurückgegeben durch manipulate/queryF etc.
		// Es gibt aber keine separate Methode – also evtl. nicht sinnvoll nutzbar
		return 0; // Dummy
	}

	public function insertId() {
		// ILIAS verwendet keine auto-increment IDs über mysqli_insert_id()
		return 0; // Dummy
	}

	public function escape($str) {
		$str = (string)$str;
		$str = str_replace(
			["\\",   "\x00", "\n",  "\r",  "'",   '"',  "\x1a"],
			["\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"],
			$str
		);

		return $str;
	}

	public function isError() {
		// ILIAS wirft Exceptions bei Fehlern – kein Error-Flag vorhanden
		return false;
	}

	public function errorNumber() {
		// Nicht verfügbar
		return 0;
	}

	public function errorMessage() {
		// Nicht verfügbar
		return "";
	}
}

