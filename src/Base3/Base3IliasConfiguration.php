<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Configuration\Api\IConfiguration;
use Base3\Database\Api\IDatabase;

class Base3IliasConfiguration implements IConfiguration {

	public function __construct(private readonly IDatabase $database) {}

	// Implementation of IConfiguration

	public function get($configuration = '') {
		$conf = $this->loadConfiguration();
		if ($configuration !== '' && isset($conf[$configuration])) {
			return $conf[$configuration];
		}
		return $conf;
	}

	public function set($data, $configuration = "") {
		// TODO: Implement set() method.
	}

	public function save() {
		// TODO: Implement save() method.
	}

	// Private methods

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
			],
			'database' => [
				'host' => '',
				'user' => '',
				'pass' => '',
				'name' => ''
			]
		];
	}

	private function loadConfiguration(): array {
		$this->database->connect();
		if (!$this->database->connected()) {
			return $this->getDefaultConfiguration();
		}

		if (!$this->tableExists()) {
			$this->createAndSeedTable();
			return $this->getDefaultConfiguration();
		}

		$config = $this->fetchConfigurationFromDatabase();
		$defaults = $this->getDefaultConfiguration();

		// Ergänze fehlende Konfigurationswerte
		foreach ($defaults as $group => $entries) {
			foreach ($entries as $name => $value) {
				if (!isset($config[$group]) || !array_key_exists($name, $config[$group])) {
					$this->insertConfigValue($group, $name, $value);
					$config[$group][$name] = $value;
				}
			}
		}

		return $config;
	}

	private function tableExists(): bool {
		$query = "SHOW TABLES LIKE 'base3ilias_configuration'";
		$result = $this->database->listQuery($query);
		return !empty($result);
	}

	private function createAndSeedTable(): void {
		$this->database->nonQuery("
			CREATE TABLE `base3ilias_configuration` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`group` varchar(100) NOT NULL,
				`name` varchar(100) NOT NULL,
				`value` varchar(100) NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `group` (`group`, `name`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
		");

		$defaults = $this->getDefaultConfiguration();
		foreach ($defaults as $group => $entries) {
			foreach ($entries as $name => $value) {
				$this->insertConfigValue($group, $name, $value);
			}
		}
	}

	private function insertConfigValue(string $group, string $name, string $value): void {
		$g = $this->database->escape($group);
		$n = $this->database->escape($name);
		$v = $this->database->escape($value);

		$this->database->nonQuery("
			INSERT INTO `base3ilias_configuration` (`group`, `name`, `value`)
			VALUES ('$g', '$n', '$v')
			ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
		");
	}

	private function fetchConfigurationFromDatabase(): array {
		$query = "SELECT `group`, `name`, `value` FROM `base3ilias_configuration`";
		$rows = $this->database->multiQuery($query);

		$config = [];
		foreach ($rows as $row) {
			$group = $row['group'];
			$name = $row['name'];
			$value = $row['value'];
			if (!isset($config[$group])) {
				$config[$group] = [];
			}
			$config[$group][$name] = $value;
		}
		return $config;
	}
}

