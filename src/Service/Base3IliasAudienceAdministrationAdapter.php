<?php declare(strict_types=1);

namespace Base3Ilias\Service;

use Base3\Api\IClassMap;
use Base3\Authorization\Api\IFeature;
use Base3\Usermanager\Api\IUsermanager;
use InvalidArgumentException;
use RuntimeException;
use UiFoundation\Api\IAuthorizationAudienceAdministrationService;

/** ILIAS authority adapter; uses the existing group and feature owners, no second persistence. */
final class Base3IliasAudienceAdministrationAdapter implements IAuthorizationAudienceAdministrationService {

	public function __construct(
		private readonly Base3IliasGroupConfiguration $groups,
		private readonly Base3IliasFeatureConfiguration $features,
		private readonly Base3IliasAudienceEditor $audiences,
		private readonly IClassMap $classMap,
		private readonly IUsermanager $usermanager
	) {}

	public function assertCanAdminister(): void {
		foreach ($this->usermanager->getGroups() as $group) {
			if ((string)$group->name === 'development') return;
		}
		throw new RuntimeException('BASE3 development membership required.');
	}

	public function getGroupView(): array {
		$this->assertCanAdminister();
		$entries = [];
		foreach ($this->groups->getEntries() as $entry) {
			$entries[] = [
				'name' => (string)$entry['group']->name,
				'description' => (string)$entry['group']->info,
				'editable' => $entry['editable'],
				'source' => $entry['source'],
				'rows' => $this->audiences->describe($entry['configuredCriteria']),
				'codeCriteria' => $entry['codeCriteria'],
				'expression' => $entry['criteria']
			];
		}
		return [
			'entries' => $entries,
			'defaultGroup' => (string)($this->groups->get()['default_group'] ?? ''),
			'criterionOptions' => $this->audiences->getCriteria(),
			'roleOptions' => $this->audiences->getRoleOptions(),
			'roleHint' => 'ILIAS role IDs, including local roles, are accepted.'
		];
	}

	public function replaceGroupRules(array $rows, string $defaultGroup): void {
		$this->assertCanAdminister();
		$known = [];
		foreach ($this->groups->getEntries() as $entry) if ($entry['editable']) $known[(string)$entry['group']->name] = true;
		foreach ($rows as $name => $rules) {
			if (!isset($known[$name]) || !is_array($rules)) throw new InvalidArgumentException('Unknown or locked group.');
		}
		$expressions = [];
		foreach ($known as $name => $unused) $expressions[$name] = $this->audiences->parse($rows[$name] ?? [], null, true);
		$this->groups->replaceAll($expressions, $defaultGroup);
	}

	public function createGroup(string $name, string $description): void {
		$this->assertCanAdminister();
		$this->groups->create($name, $description);
	}

	public function removeGroup(string $name): void {
		$this->assertCanAdminister();
		$this->groups->remove($name);
	}

	public function getFeatureView(): array {
		$this->assertCanAdminister();
		$catalog = $this->catalog();
		$groups = array_map(static fn($group): string => (string)$group->name, $this->groups->getGroups());
		$rows = [];
		foreach ($catalog as $feature) {
			$permissions = [];
			foreach ($feature->getPermissions() as $permission) $permissions[] = (string)$permission->scope . '/' . (string)$permission->permission;
			$rows[] = [
				'name' => $feature::getName(),
				'label' => $feature->getLabel(),
				'description' => $feature->getDescription(),
				'permissions' => $permissions,
				'rules' => $this->audiences->describe($this->features->getAudience($feature))
			];
		}
		return [
			'rows' => $rows,
			'groups' => $groups,
			'criterionOptions' => $this->audiences->getCriteria(),
			'roleOptions' => $this->audiences->getRoleOptions(),
			'roleHint' => 'ILIAS role IDs, including local roles, are accepted.'
		];
	}

	public function replaceFeatureRules(array $rows): void {
		$this->assertCanAdminister();
		$catalog = $this->catalog();
		$groups = array_map(static fn($group): string => (string)$group->name, $this->groups->getGroups());
		foreach ($rows as $name => $rules) {
			if (!isset($catalog[$name]) || !is_array($rules)) throw new InvalidArgumentException('Unknown feature.');
		}
		$expressions = [];
		foreach ($catalog as $name => $feature) {
			$expressions[$name] = $this->audiences->parse($rows[$name] ?? [], $groups, true);
		}
		$this->features->replaceAll($expressions, $groups);
	}

	/** @return array<string,IFeature> */
	private function catalog(): array {
		$features = [];
		foreach ($this->classMap->getInstancesByInterface(IFeature::class) as $feature) {
			if (!$feature instanceof IFeature) continue;
			$name = $feature::getName();
			if (isset($features[$name])) throw new InvalidArgumentException('Duplicate BASE3 feature.');
			$features[$name] = $feature;
		}
		ksort($features);
		return $features;
	}
}
