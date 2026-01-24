<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Configuration\AbstractConfiguration;
use Base3\Database\Api\IDatabase;

/**
 * Class Base3IliasConfiguration
 *
 * DB-backed configuration for ILIAS integration.
 *
 * Important:
 * - This implementation has a mandatory default configuration.
 * - On first run it creates/seeds the table and ensures defaults exist in DB.
 */
class Base3IliasConfiguration extends AbstractConfiguration {

	public function __construct(private readonly IDatabase $database) {}

	// ---------------------------------------------------------------------
	// AbstractConfiguration
	// ---------------------------------------------------------------------

	protected function load(): array {
		return $this->loadConfiguration();
	}

	protected function saveData(array $data): bool {
		// Persist the full config state to DB
		$this->database->connect();
		if (!$this->database->connected()) return false;

		if (!$this->tableExists()) {
			$this->createAndSeedTable();
		}

		foreach ($data as $group => $entries) {
			if (!is_array($entries)) continue;
			foreach ($entries as $name => $value) {
				$this->insertConfigValue((string)$group, (string)$name, $value);
			}
		}

		return true;
	}

	public function reload(): void {
		// keep behavior consistent, but re-load from DB (and ensure defaults)
		$this->cnf = null;
		$this->dirty = false;
		$this->ensureLoaded();
	}

	public function persistValue(string $group, string $key, $value): bool {
		// Optimized single-value persistence for DB
		$this->ensureLoaded();
		$this->setValue($group, $key, $value);

		$this->database->connect();
		if (!$this->database->connected()) return false;

		if (!$this->tableExists()) {
			$this->createAndSeedTable();
		}

		$this->insertConfigValue($group, $key, $value);

		// we successfully persisted, so no need to keep dirty just for this change
		$this->dirty = false;
		return true;
	}

	// ---------------------------------------------------------------------
	// Default configuration (mandatory for this impl)
	// ---------------------------------------------------------------------

	private function getDefaultConfiguration(): array {
		return [
			'base' => [
				'url' => '',
				'endpoint' => 'base3.php',
				'intern' => ''
			],
			'manager' => [
				'stdscope' => 'web',
				'layout' => 'simple'
			]
		];
	}

	// ---------------------------------------------------------------------
	// DB load/ensure defaults
	// ---------------------------------------------------------------------

	private function loadConfiguration(): array {
		$this->database->connect();
		if (!$this->database->connected()) {
			// No DB: return defaults (still required)
			return $this->getDefaultConfiguration();
		}

		if (!$this->tableExists()) {
			$this->createAndSeedTable();
			return $this->getDefaultConfiguration();
		}

		$config = $this->fetchConfigurationFromDatabase();
		$defaults = $this->getDefaultConfiguration();

		// Ensure defaults exist in DB + in returned array
		foreach ($defaults as $group => $entries) {
			if (!is_array($entries)) continue;

			foreach ($entries as $name => $value) {
				if (!isset($config[$group]) || !array_key_exists($name, $config[$group])) {
					$this->insertConfigValue((string)$group, (string)$name, $value);
					if (!isset($config[$group]) || !is_array($config[$group])) $config[$group] = [];
					$config[$group][$name] = $value;
				}
			}
		}

		return $config;
	}

	private function tableExists(): bool {
		$query = "SHOW TABLES LIKE 'base3_configuration'";
		$result = $this->database->listQuery($query);
		return !empty($result);
	}

	private function createAndSeedTable(): void {
		$this->database->nonQuery("
			CREATE TABLE `base3_configuration` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`group` varchar(100) NOT NULL,
				`name` varchar(100) NOT NULL,
				`value` text NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `group` (`group`, `name`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
		");

		$defaults = $this->getDefaultConfiguration();
		foreach ($defaults as $group => $entries) {
			if (!is_array($entries)) continue;
			foreach ($entries as $name => $value) {
				$this->insertConfigValue((string)$group, (string)$name, $value);
			}
		}
	}

	private function insertConfigValue(string $group, string $name, $value): void {
		$g = $this->database->escape($group);
		$n = $this->database->escape($name);

		// Store arrays/objects as JSON for robustness
		if (is_array($value) || is_object($value)) {
			$value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		if ($value === null) $value = '';

		$v = $this->database->escape((string)$value);

		$this->database->nonQuery("
			INSERT INTO `base3_configuration` (`group`, `name`, `value`)
			VALUES ('$g', '$n', '$v')
			ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
		");
	}

	private function fetchConfigurationFromDatabase(): array {
		$query = "SELECT `group`, `name`, `value` FROM `base3_configuration`";
		$rows = $this->database->multiQuery($query);

		$config = [];
		foreach ($rows as $row) {
			$group = $row['group'];
			$name = $row['name'];
			$value = $row['value'];

			// If value is JSON, decode to array (optional convenience)
			if (is_string($value)) {
				$trim = trim($value);
				if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
					$decoded = json_decode($trim, true);
					if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
				}
			}

			if (!isset($config[$group]) || !is_array($config[$group])) {
				$config[$group] = [];
			}
			$config[$group][$name] = $value;
		}

		return $config;
	}

}
