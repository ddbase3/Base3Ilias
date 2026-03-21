<?php declare(strict_types=1);

namespace Base3Ilias\Api;

interface IPageComponentConfigStore {

	/**
	 * Loads the stored config for a page component instance.
	 * Must enforce ref_id and component_type matching to prevent id swapping.
	 *
	 * @return array<string,mixed>
	 */
	public function load(string $component_type, string $instance_id, int $ref_id): array;

	/**
	 * Saves (upserts) the config for a page component instance.
	 *
	 * @param array<string,mixed> $config
	 */
	public function save(string $component_type, string $instance_id, int $ref_id, array $config): void;

	/**
	 * Returns true if an entry exists for the given instance.
	 */
	public function exists(string $component_type, string $instance_id, int $ref_id): bool;

	/**
	 * Deletes a stored config entry.
	 */
	public function delete(string $component_type, string $instance_id, int $ref_id): void;
}
