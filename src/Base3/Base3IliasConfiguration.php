<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Configuration\Database\DatabaseConfiguration;
use Base3\Database\Api\IDatabase;

class Base3IliasConfiguration extends DatabaseConfiguration {

	public function __construct(IDatabase $database) {
		parent::__construct($database);
	}

	protected function load(): array {
		$data = parent::load();

		return $this->mergeDefaults($data, $this->getDefaultConfiguration());
	}

	private function getDefaultConfiguration(): array {
		return [
			'base' => [
				'endpoint' => 'index.php?baseClass=ilUIPluginRouterGUI&cmdClass=ilBase3IliasAdapterAjaxGUI&cmd=dispatch',
				'url' => $this->resolveBaseUrl()
			]
		];
	}

	private function mergeDefaults(array $data, array $defaults): array {
		foreach ($defaults as $group => $entries) {
			if (!isset($data[$group]) || !is_array($data[$group])) {
				$data[$group] = [];
			}

			foreach ($entries as $key => $value) {
				if (!array_key_exists($key, $data[$group])) {
					$data[$group][$key] = $value;
				}
			}
		}

		return $data;
	}

	private function resolveBaseUrl(): string {
		if (defined('ILIAS_HTTP_PATH') && is_string(ILIAS_HTTP_PATH) && ILIAS_HTTP_PATH !== '') {
			return rtrim(ILIAS_HTTP_PATH, '/') . '/';
		}

		$scheme = $this->resolveScheme();
		$host = $_SERVER['HTTP_HOST'] ?? '';

		if ($host === '') {
			return '/';
		}

		$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
		$basePath = trim(str_replace('\\', '/', dirname($scriptName)), '/');

		if ($basePath === '' || $basePath === '.') {
			return $scheme . '://' . $host . '/';
		}

		return $scheme . '://' . $host . '/' . $basePath . '/';
	}

	private function resolveScheme(): string {
		if (
			isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
			strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
		) {
			return 'https';
		}

		if (
			isset($_SERVER['HTTPS']) &&
			($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')
		) {
			return 'https';
		}

		return 'http';
	}
}
