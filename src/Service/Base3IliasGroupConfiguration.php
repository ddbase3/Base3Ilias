<?php declare(strict_types=1);

namespace Base3Ilias\Service;

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\Api\IGroup;
use Base3\Usermanager\Group;
use Base3\Usermanager\UserCriterionEvaluator;
use InvalidArgumentException;

/** One ILIAS-owned group catalog: discovered code definitions plus host-managed group DTOs. */
final class Base3IliasGroupConfiguration {

	public const SETTINGS_GROUP = 'base3ilias';
	public const SETTINGS_NAME = 'groups';

	private ?array $cached = null;
	private ?array $definitions = null;

	public function __construct(
		private readonly ISettingsStore $store,
		private readonly IClassMap $classMap,
		private readonly UserCriterionEvaluator $evaluator,
		private readonly Base3IliasFeatureConfiguration $features
	) {}

	/**
	 * Returns the canonical ILIAS group settings. Locked code groups are never persisted here.
	 * The historical assignment format requires a versioned migration, not runtime conversion.
	 * @return array<string,mixed> Settings with criteria, optional groups and optional default_group
	 * @throws \RuntimeException When the pre-existing migration has not run
	 */
	public function get(): array {
		if ($this->cached !== null) return $this->cached;
		$settings = $this->store->get(self::SETTINGS_GROUP, self::SETTINGS_NAME, []);
		if (array_key_exists('assignments', $settings)) throw new \RuntimeException('Group settings require the versioned BASE3 criteria migration.');
		return $this->cached = $settings;
	}

	/**
	 * Converts the previous ILIAS user/role ID membership assignments into criterion expressions.
	 * Invoked exclusively from the immutable migration step, not from a permission check.
	 * @return void
	 * @throws InvalidArgumentException On an ambiguous mixed source schema
	 */
	public function migrateLegacy(): void {
		$settings = $this->store->get(self::SETTINGS_GROUP, self::SETTINGS_NAME, []);
		if (!array_key_exists('assignments', $settings)) return;
		if (array_key_exists('criteria', $settings)) throw new InvalidArgumentException('Group settings contain conflicting schemas.');
		$criteria = [];
		foreach ((array)$settings['assignments'] as $name => $entry) {
			if (!is_array($entry)) continue;
			$items = [];
			$userIds = $this->positiveIds($entry['user_ids'] ?? []);
			$roleIds = $this->positiveIds($entry['role_ids'] ?? []);
			if ($userIds !== []) $items[] = ['criterion' => 'base3useridcriterion', 'config' => ['user_ids' => $userIds]];
			if ($roleIds !== []) $items[] = ['criterion' => 'base3iliasrolecriterion', 'config' => ['role_ids' => $roleIds]];
			$criteria[$name] = $items !== [] ? ['any' => $items] : [];
		}
		$converted = ['criteria' => $criteria, 'default_group' => trim((string)($settings['default_group'] ?? ''))];
		$this->store->set(self::SETTINGS_GROUP, self::SETTINGS_NAME, $converted);
		$this->store->save();
		$this->cached = null;
	}

	/**
	 * Discovers class-owned group definitions through IClassMap. User-created groups are data,
	 * not implementations of IGroup, and must never shadow a discovered technical name.
	 * @return list<IGroup> Unique code-owned group definitions
	 * @throws InvalidArgumentException On duplicate or empty names
	 */
	public function getDefinitions(): array {
		if ($this->definitions !== null) return $this->definitions;
		$byName = [];
		foreach ($this->classMap->getInstancesByInterface(IGroup::class) as $definition) {
			if (!$definition instanceof IGroup) continue;
			$name = trim((string)$definition->getGroup()->name);
			if ($name === '' || isset($byName[$name])) throw new InvalidArgumentException('Duplicate or empty BASE3 group name.');
			$byName[$name] = $definition;
		}
		ksort($byName);
		return $this->definitions = array_values($byName);
	}

	/**
	 * Returns effective group descriptions without creating a second group registry. One row
	 * represents either a locked/discoverable code group or one editable host-managed DTO.
	 * @return list<array{group:Group,criteria:array,codeCriteria:array,configuredCriteria:array,editable:bool,source:string}>
	 * @throws InvalidArgumentException On name collisions or malformed host definitions
	 */
	public function getEntries(): array {
		$settings = $this->get();
		$stored = $settings['groups'] ?? [];
		if (!is_array($stored)) throw new InvalidArgumentException('Invalid managed BASE3 group definitions.');
		$entries = [];
		foreach ($this->getDefinitions() as $definition) {
			$group = $definition->getGroup();
			$name = (string)$group->name;
			$codeRules = $definition->getCriteria();
			$configuredRules = $definition->isEditable() ? ($settings['criteria'][$name] ?? []) : [];
			if (!is_array($codeRules) || !is_array($configuredRules)) throw new InvalidArgumentException('Invalid BASE3 group criteria.');
			// Code-owned membership is a fixed baseline. Host settings may add members but cannot
			// replace the bootstrap definition, even when the editable expression is empty.
			$rules = $codeRules === [] ? $configuredRules : ($configuredRules === [] ? $codeRules : ['any' => [$codeRules, $configuredRules]]);
			$entries[$name] = [
				'group' => $group, 'criteria' => $rules, 'codeCriteria' => $codeRules,
				'configuredCriteria' => $configuredRules, 'editable' => $definition->isEditable(), 'source' => 'code'
			];
		}
		foreach ($stored as $name => $item) {
			if (!is_string($name) || !$this->isValidName($name) || isset($entries[$name]) || !is_array($item)
				|| !isset($item['description']) || !is_string($item['description']) || !is_array($item['criteria'] ?? null)) {
				throw new InvalidArgumentException('Invalid or conflicting managed BASE3 group.');
			}
			$group = Group::fromArray(['id' => $name, 'name' => $name, 'info' => $item['description'], 'archive' => 0, 'roles' => []]);
			$entries[$name] = [
				'group' => $group, 'criteria' => $item['criteria'], 'codeCriteria' => [],
				'configuredCriteria' => $item['criteria'], 'editable' => true, 'source' => 'managed'
			];
		}
		ksort($entries);
		return array_values($entries);
	}

	/** @return list<Group> The same effective BASE3 groups used by runtime and both admin UIs */
	public function getGroups(): array {
		return array_column($this->getEntries(), 'group');
	}

	/**
	 * Writes all editable group memberships together; runtime group definitions remain stored
	 * in the original ILIAS settings dataset. Code-defined criteria are never overwritten.
	 * @param array<string,array> $rules Logical group names to complete membership expressions
	 * @param string $defaultGroup Optional legacy fallback, never development/administration
	 * @return void
	 * @throws InvalidArgumentException On unknown groups or invalid expressions
	 */
	public function replaceAll(array $rules, string $defaultGroup = ''): void {
		$settings = $this->get();
		$entries = $this->getEntries();
		$editable = [];
		$codeNames = [];
		foreach ($entries as $entry) {
			$name = (string)$entry['group']->name;
			if ($entry['editable']) $editable[$name] = $entry['source'];
			if ($entry['source'] === 'code') $codeNames[$name] = true;
		}
		if ($defaultGroup !== '' && (!isset($editable[$defaultGroup]) || in_array($defaultGroup, ['development', 'administration'], true))) {
			throw new InvalidArgumentException('Invalid default BASE3 group.');
		}
		foreach ($rules as $name => $expression) {
			if (!isset($editable[$name]) || !is_array($expression)) throw new InvalidArgumentException('Unknown or locked BASE3 group.');
			$this->evaluator->validate($expression);
		}
		foreach ($entries as $entry) {
			if (!$entry['editable']) continue;
			$name = (string)$entry['group']->name;
			$expression = $rules[$name] ?? [];
			if (isset($codeNames[$name])) $settings['criteria'][$name] = $expression;
			else $settings['groups'][$name]['criteria'] = $expression;
		}
		$settings['default_group'] = $defaultGroup;
		$this->persist($settings);
	}

	/**
	 * Creates one runtime-configured BASE3 group without inventing a PHP class or modifying
	 * native ILIAS groups. The name must be unique across all discoverable and managed groups.
	 * @param string $name Stable lowercase technical name
	 * @param string $description Human-readable label or explanation
	 * @return void
	 * @throws InvalidArgumentException On duplicates, invalid names or descriptions
	 */
	public function create(string $name, string $description): void {
		$name = trim($name);
		$description = trim($description);
		if (!$this->isValidName($name) || $description === '' || mb_strlen($description) > 250) throw new InvalidArgumentException('Invalid BASE3 group name or description.');
		foreach ($this->getEntries() as $entry) {
			if ((string)$entry['group']->name === $name) throw new InvalidArgumentException('BASE3 group name already exists.');
		}
		$settings = $this->get();
		$settings['groups'][$name] = ['description' => $description, 'criteria' => []];
		$this->persist($settings);
	}

	/**
	 * Deletes a host-created group only if no feature or fallback still references it.
	 * Code-discovered groups are never removable through the host settings UI.
	 * @param string $name Managed group identifier
	 * @return void
	 * @throws InvalidArgumentException If the group is unknown, code-owned or referenced
	 */
	public function remove(string $name): void {
		$settings = $this->get();
		if (!isset($settings['groups'][$name]) || $name === (string)($settings['default_group'] ?? '')
			|| $this->features->referencesGroup($name)) throw new InvalidArgumentException('Group is not removable or still in use.');
		unset($settings['groups'][$name]);
		$this->persist($settings);
	}

	private function persist(array $settings): void {
		$this->store->set(self::SETTINGS_GROUP, self::SETTINGS_NAME, $settings);
		$this->store->save();
		$this->cached = $settings;
	}

	private function isValidName(string $name): bool {
		return (bool)preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $name);
	}

	private function positiveIds(mixed $value): array {
		if (!is_array($value)) return [];
		$ids = [];
		foreach ($value as $id) if (is_numeric($id) && (int)$id > 0) $ids[] = (int)$id;
		$ids = array_values(array_unique($ids));
		sort($ids);
		return $ids;
	}
}
