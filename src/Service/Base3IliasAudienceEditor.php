<?php declare(strict_types=1);

namespace Base3Ilias\Service;

use Base3\Api\IClassMap;
use Base3\Usermanager\Api\IUserCriterion;
use Base3\Usermanager\UserCriterionEvaluator;
use InvalidArgumentException;
use ilObject;
use ilRbacReview;

/** Translates editable ILIAS form rows into the ONE BASE3 criterion expression grammar. */
final class Base3IliasAudienceEditor {

	public function __construct(
		private readonly IClassMap $classMap,
		private readonly UserCriterionEvaluator $evaluator,
		private readonly ilRbacReview $rbacreview
	) {}

	/**
	 * Lists plugin criteria and their UI schemas; no separate registry or persistence.
	 * @return list<array{name:string,schema:array}> Sorted discoverable predicates
	 * @throws InvalidArgumentException On duplicate logical predicate names
	 */
	public function getCriteria(): array {
		$found = [];
		foreach ($this->classMap->getInstancesByInterface(IUserCriterion::class) as $criterion) {
			if (!$criterion instanceof IUserCriterion) continue;
			$name = $criterion::getName();
			if (isset($found[$name])) throw new InvalidArgumentException('Duplicate BASE3 criterion.');
			$found[$name] = ['name' => $name, 'schema' => $criterion->getSchema()];
		}
		ksort($found);
		return array_values($found);
	}

	/**
	 * Returns ILIAS's global role names as UI suggestions, without copying host roles
	 * into a BASE3 registry. Local roles remain addressable by their native object ID.
	 * @return list<array{id:int,label:string}>
	 */
	public function getRoleOptions(): array {
		$ids = array_unique(array_map('intval', $this->rbacreview->getGlobalRoles()));
		sort($ids);
		$options = [];
		foreach ($ids as $id) {
			if ($id <= 0) continue;
			$options[] = ['id' => $id, 'label' => trim((string)ilObject::_lookupTitle($id))];
		}
		return $options;
	}

	/**
	 * Parses a repeatable assignment form for groups or features. Every item is OR-connected;
	 * existing complex AND/NOT rules are represented as one advanced expression item without loss.
	 * User and ILIAS-role rows become discoverable criteria, not a parallel membership mechanism.
	 * @param array $rows Submitted assignment entries
	 * @param list<string>|null $groups Group names for features; null forbids group references
	 * @param bool $allowRoles Whether native ILIAS role rows are permitted in this view
	 * @return array<string,mixed> A valid single BASE3 audience expression
	 * @throws InvalidArgumentException On bad identifiers, unknown criteria or invalid JSON
	 */
	public function parse(array $rows, ?array $groups, bool $allowRoles): array {
		if (count($rows) > 100) throw new InvalidArgumentException('Too many BASE3 audience rules.');
		$items = [];
		foreach ($rows as $row) {
			if (!is_array($row) || !is_string($row['kind'] ?? null)) throw new InvalidArgumentException('Invalid BASE3 audience row.');
			$kind = $row['kind'];
			$value = $row['value'] ?? '';
			$config = $row['config'] ?? '{}';
			switch ($kind) {
				case 'user':
					$id = $this->positiveId($value);
					$items[] = ['criterion' => 'base3useridcriterion', 'config' => ['user_ids' => [$id]]];
					break;
				case 'role':
					if (!$allowRoles) throw new InvalidArgumentException('Native roles are not allowed in this audience.');
					$id = $this->positiveId($value);
					$items[] = ['criterion' => 'base3iliasrolecriterion', 'config' => ['role_ids' => [$id]]];
					break;
				case 'group':
					if ($groups === null || !is_string($value) || !in_array($value, $groups, true)) throw new InvalidArgumentException('Unknown BASE3 group.');
					$items[] = ['group' => $value];
					break;
				case 'criterion':
					if (!is_string($value) || $value === '') throw new InvalidArgumentException('Invalid criterion name.');
					$items[] = ['criterion' => $value, 'config' => $this->parseJson($config)];
					break;
				case 'expression':
					$items[] = $this->parseJson($config);
					break;
				default:
					throw new InvalidArgumentException('Unknown BASE3 audience row type.');
			}
		}
		$expression = $items === [] ? [] : ['any' => $items];
		$this->evaluator->validate($expression, $groups);
		return $expression;
	}

	/**
	 * Describes an expression as editable repeated rows without changing its meaning.
	 * Nested complex expressions remain one advanced JSON row and round-trip unchanged.
	 * @param array<string,mixed> $expression Existing audience expression
	 * @return list<array{kind:string,value:string,config:string}>
	 */
	public function describe(array $expression): array {
		if ($expression === []) return [];
		$items = array_keys($expression) === ['any'] && is_array($expression['any']) ? $expression['any'] : [$expression];
		$rows = [];
		foreach ($items as $item) {
			if (!is_array($item)) throw new InvalidArgumentException('Invalid persisted audience expression.');
			if (array_keys($item) === ['group'] && is_string($item['group'])) {
				$rows[] = ['kind' => 'group', 'value' => $item['group'], 'config' => '{}'];
				continue;
			}
			if (isset($item['criterion'])) {
				$name = (string)$item['criterion'];
				$config = (array)($item['config'] ?? []);
				if ($name === 'base3useridcriterion' && count($config['user_ids'] ?? []) === 1) {
					$rows[] = ['kind' => 'user', 'value' => (string)$config['user_ids'][0], 'config' => '{}'];
					continue;
				}
				if ($name === 'base3iliasrolecriterion' && count($config['role_ids'] ?? []) === 1) {
					$rows[] = ['kind' => 'role', 'value' => (string)$config['role_ids'][0], 'config' => '{}'];
					continue;
				}
				$rows[] = ['kind' => 'criterion', 'value' => $name, 'config' => json_encode($config === [] ? (object)[] : $config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)];
				continue;
			}
			$rows[] = ['kind' => 'expression', 'value' => '', 'config' => json_encode($item, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)];
		}
		return $rows;
	}

	private function parseJson(mixed $value): array {
		if (!is_string($value) || strlen($value) > 20000) throw new InvalidArgumentException('Invalid audience JSON configuration.');
		try {
			$data = json_decode($value, true, 20, JSON_THROW_ON_ERROR);
		} catch (\JsonException $error) {
			throw new InvalidArgumentException('Audience configuration must contain valid JSON.', 0, $error);
		}
		if (!is_array($data) || array_is_list($data) && $data !== []) throw new InvalidArgumentException('Audience JSON must describe an object.');
		return $data;
	}

	private function positiveId(mixed $value): int {
		if (!is_string($value) && !is_int($value)) throw new InvalidArgumentException('Invalid native ID.');
		$id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if ($id === false) throw new InvalidArgumentException('Expected positive ILIAS ID.');
		return $id;
	}
}
