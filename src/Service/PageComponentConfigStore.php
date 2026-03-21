<?php declare(strict_types=1);

namespace Base3Ilias\Service;

use Base3\Database\Api\IDatabase;
use Base3Ilias\Api\IPageComponentConfigStore;
use RuntimeException;

class PageComponentConfigStore implements IPageComponentConfigStore {

	protected IDatabase $db;

	public function __construct(IDatabase $db) {
		$this->db = $db;
	}

	public function load(string $component_type, string $instance_id, int $ref_id): array {
		$row = $this->db->singleQuery(
			"SELECT config_json, component_type, ref_id
			 FROM base3_pagecomponent_config
			 WHERE instance_id = " . $this->db->escape($instance_id)
		);

		if (!$row) {
			return [];
		}

		// Prevent ID swapping: instance_id must match the expected component_type + ref_id.
		if ((string)$row['component_type'] !== $component_type || (int)$row['ref_id'] !== $ref_id) {
			throw new RuntimeException("PageComponent config mismatch (instance_id does not belong to this context).");
		}

		$json = (string)($row['config_json'] ?? '');
		if ($json === '') {
			return [];
		}

		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			throw new RuntimeException("Invalid JSON in base3_pagecomponent_config for instance_id: " . $instance_id);
		}

		return $decoded;
	}

	public function save(string $component_type, string $instance_id, int $ref_id, array $config): void {
		$json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new RuntimeException("Failed to encode page component config to JSON.");
		}

		$now = date('Y-m-d H:i:s');

		// MySQL upsert
		$sql = "INSERT INTO base3_pagecomponent_config
			(instance_id, component_type, ref_id, config_json, created_at, updated_at)
			VALUES (
				" . $this->db->escape($instance_id) . ",
				" . $this->db->escape($component_type) . ",
				" . (int)$ref_id . ",
				" . $this->db->escape($json) . ",
				" . $this->db->escape($now) . ",
				" . $this->db->escape($now) . "
			)
			ON DUPLICATE KEY UPDATE
				component_type = VALUES(component_type),
				ref_id = VALUES(ref_id),
				config_json = VALUES(config_json),
				updated_at = VALUES(updated_at)";

		$this->db->multiQuery($sql);
	}

	public function exists(string $component_type, string $instance_id, int $ref_id): bool {
		$row = $this->db->singleQuery(
			"SELECT instance_id, component_type, ref_id
			 FROM base3_pagecomponent_config
			 WHERE instance_id = " . $this->db->escape($instance_id)
		);

		if (!$row) {
			return false;
		}

		return ((string)$row['component_type'] === $component_type) && ((int)$row['ref_id'] === $ref_id);
	}

	public function delete(string $component_type, string $instance_id, int $ref_id): void {
		// Only delete if it belongs to the same context (prevents deleting foreign configs).
		$row = $this->db->singleQuery(
			"SELECT component_type, ref_id
			 FROM base3_pagecomponent_config
			 WHERE instance_id = " . $this->db->escape($instance_id)
		);

		if (!$row) {
			return;
		}

		if ((string)$row['component_type'] !== $component_type || (int)$row['ref_id'] !== $ref_id) {
			throw new RuntimeException("PageComponent config mismatch (refusing to delete foreign entry).");
		}

		$this->db->multiQuery(
			"DELETE FROM base3_pagecomponent_config
			 WHERE instance_id = " . $this->db->escape($instance_id)
		);
	}
}
