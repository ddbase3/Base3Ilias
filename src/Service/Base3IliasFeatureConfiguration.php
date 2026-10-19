<?php declare(strict_types=1);

namespace Base3Ilias\Service;

use Base3\Authorization\Api\IFeature;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\UserCriterionEvaluator;
use InvalidArgumentException;

/** One ILIAS-owned settings dataset for complete BASE3 feature audience expressions. */
final class Base3IliasFeatureConfiguration {

	public const SETTINGS_GROUP = 'base3_authorization_features';
	public const SETTINGS_NAME = 'catalog';

	private ?array $rules = null;

	public function __construct(
		private readonly ISettingsStore $store,
		private readonly UserCriterionEvaluator $evaluator,
		private readonly IClassMap $classMap
	) {}

	/**
	 * Returns the code-defined audience unless an explicit host override is stored. A saved
	 * empty expression is an explicit denial, not a request to restore defaults.
	 * @param IFeature $feature Discovered feature description
	 * @return array<string,mixed> Complete criterion/group audience expression
	 * @throws InvalidArgumentException On malformed persisted settings
	 */
	public function getAudience(IFeature $feature): array {
		$rules = $this->load();
		$name = $feature::getName();
		return array_key_exists($name, $rules) ? $rules[$name] : $feature->getDefaultAudience();
	}

	/**
	 * Replaces all host-overridden feature audiences with one host-storage write. Native ILIAS
	 * object RBAC is unaffected. Validation rejects unknown groups or criteria before writing.
	 * @param array<string,array> $rules Exact discovered feature names to audience expressions
	 * @param list<string> $groups All currently known BASE3 group names
	 * @return void
	 * @throws InvalidArgumentException If a rule is malformed or references an unknown group
	 */
	public function replaceAll(array $rules, array $groups): void {
		foreach ($rules as $name => $expression) {
			if (!is_string($name) || $name === '' || !is_array($expression)) throw new InvalidArgumentException('Invalid feature audience definition.');
			$this->evaluator->validate($expression, $groups);
		}
		$this->store->set(self::SETTINGS_GROUP, self::SETTINGS_NAME, ['rules' => $rules]);
		$this->store->save();
		$this->rules = $rules;
	}

	/**
	 * Converts the historical persisted group-name grants into the same expression format
	 * used by groups and direct criteria. Only the versioned migration calls this operation.
	 * @return void
	 * @throws InvalidArgumentException On conflicting schemas or malformed previous grants
	 */
	public function migrateLegacy(): void {
		$data = $this->store->get(self::SETTINGS_GROUP, self::SETTINGS_NAME, []);
		if (!array_key_exists('grants', $data)) return;
		if (array_key_exists('rules', $data) || !is_array($data['grants'])) throw new InvalidArgumentException('Conflicting feature audience schemas.');
		$rules = [];
		foreach ($data['grants'] as $feature => $groups) {
			if (!is_string($feature) || !is_array($groups) || !array_is_list($groups)) throw new InvalidArgumentException('Invalid legacy feature groups.');
			$members = [];
			foreach ($groups as $group) {
				if (!is_string($group) || trim($group) === '') throw new InvalidArgumentException('Invalid legacy feature group name.');
				$members[] = ['group' => $group];
			}
			$rules[$feature] = $members !== [] ? ['any' => $members] : [];
		}
		$this->store->set(self::SETTINGS_GROUP, self::SETTINGS_NAME, ['rules' => $rules]);
		$this->store->save();
		$this->rules = null;
	}

	/**
	 * Prevents deletion of a host-defined group still referenced by an explicit feature rule.
	 * @param string $name Exact logical group name
	 * @return bool Whether stored feature expressions reference it
	 */
	public function referencesGroup(string $name): bool {
		$overrides = $this->load();
		foreach ($this->classMap->getInstancesByInterface(IFeature::class) as $feature) {
			if (!$feature instanceof IFeature) continue;
			$key = $feature::getName();
			$expression = array_key_exists($key, $overrides) ? $overrides[$key] : $feature->getDefaultAudience();
			if ($this->containsGroup($expression, $name)) return true;
		}
		return false;
	}

	/** @return array<string,array> */
	private function load(): array {
		if ($this->rules !== null) return $this->rules;
		$data = $this->store->get(self::SETTINGS_GROUP, self::SETTINGS_NAME, []);
		if (array_key_exists('grants', $data)) throw new \RuntimeException('Feature settings require the versioned audience migration.');
		$rules = $data['rules'] ?? [];
		if (!is_array($rules) || array_is_list($rules) && $rules !== []) throw new InvalidArgumentException('Invalid feature rule catalog.');
		foreach ($rules as $name => $expression) {
			if (!is_string($name) || !is_array($expression)) throw new InvalidArgumentException('Invalid stored feature rule.');
		}
		return $this->rules = $rules;
	}

	private function containsGroup(array $expression, string $name): bool {
		if (array_key_exists('group', $expression)) return $expression['group'] === $name;
		foreach (['any', 'all'] as $op) {
			foreach (($expression[$op] ?? []) as $item) {
				if (is_array($item) && $this->containsGroup($item, $name)) return true;
			}
		}
		return isset($expression['not']) && is_array($expression['not']) && $this->containsGroup($expression['not'], $name);
	}
}
