<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use ilObject;
use ilTree;

final class IliasObjectDebugDisplay implements IDisplay {

	private const PARAM_TARGET_REF_ID = 'base3_object_ref_id';
	private const MAX_CHILDREN = 100;

	public function __construct(
		private readonly IMvcView $view,
		private readonly ilTree $tree
	) {}

	public static function getName(): string {
		return 'iliasobjectdebugdisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		return 'ILIAS object and repository node debug overview.';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$targetRefId = $this->getRequestTargetRefId();

		$this->view->setPath(\DIR_COMPONENTS . 'Base3/Base3Ilias');
		$this->view->setTemplate('Display/IliasObjectDebugDisplay.php');

		$this->view->assign('generatedAt', date('c'));
		$this->view->assign('targetRefId', $targetRefId);
		$this->view->assign('targetParamName', self::PARAM_TARGET_REF_ID);
		$this->view->assign('objectRows', $this->getObjectRows($targetRefId));
		$this->view->assign('pathRows', $this->getPathRows($targetRefId));
		$this->view->assign('childRows', $this->getChildRows($targetRefId));
		$this->view->assign('maxChildren', self::MAX_CHILDREN);

		return $this->view->loadTemplate();
	}

	private function getObjectRows(int $targetRefId): array {
		if ($targetRefId <= 0) {
			return [
				$this->row('Target Ref ID', self::PARAM_TARGET_REF_ID, ''),
				$this->row('Status', 'target', 'No target ref_id given. Use ' . self::PARAM_TARGET_REF_ID . ' to inspect an object.'),
			];
		}

		$objId = ilObject::_lookupObjId($targetRefId);
		$type = ilObject::_lookupType($targetRefId, true);
		$title = $objId > 0 ? ilObject::_lookupTitle($objId) : '';

		return [
			$this->row('Target Ref ID', self::PARAM_TARGET_REF_ID, $targetRefId),
			$this->row('Object ID', 'ilObject::_lookupObjId()', $objId),
			$this->row('Object Type', 'ilObject::_lookupType(ref_id, true)', $type),
			$this->row('Title', 'ilObject::_lookupTitle(obj_id)', $title),
			$this->row('Valid Object', 'obj_id > 0', $objId > 0 ? 'yes' : 'no'),
		];
	}

	private function getPathRows(int $targetRefId): array {
		if ($targetRefId <= 0) {
			return [];
		}

		try {
			$path = $this->tree->getPathFull($targetRefId);
		} catch (\Throwable $e) {
			return [
				[
					'depth' => '',
					'ref_id' => '',
					'obj_id' => '',
					'type' => '',
					'title' => 'Path could not be loaded: ' . $e->getMessage(),
				],
			];
		}

		$rows = [];

		foreach ($path as $node) {
			$rows[] = [
				'depth' => $this->value($node, 'depth'),
				'ref_id' => $this->value($node, 'child'),
				'obj_id' => $this->value($node, 'obj_id'),
				'type' => $this->value($node, 'type'),
				'title' => $this->value($node, 'title'),
			];
		}

		return $rows;
	}

	private function getChildRows(int $targetRefId): array {
		if ($targetRefId <= 0) {
			return [];
		}

		try {
			$children = $this->tree->getChilds($targetRefId);
		} catch (\Throwable $e) {
			return [
				[
					'ref_id' => '',
					'obj_id' => '',
					'type' => '',
					'title' => 'Children could not be loaded: ' . $e->getMessage(),
					'description' => '',
				],
			];
		}

		$rows = [];

		foreach (array_slice($children, 0, self::MAX_CHILDREN) as $node) {
			$rows[] = [
				'ref_id' => $this->value($node, 'child'),
				'obj_id' => $this->value($node, 'obj_id'),
				'type' => $this->value($node, 'type'),
				'title' => $this->value($node, 'title'),
				'description' => $this->value($node, 'description'),
			];
		}

		return $rows;
	}

	private function getRequestTargetRefId(): int {
		$params = [];
		parse_str($this->serverValue('QUERY_STRING'), $params);

		if (isset($params[self::PARAM_TARGET_REF_ID]) && is_numeric($params[self::PARAM_TARGET_REF_ID])) {
			return (int)$params[self::PARAM_TARGET_REF_ID];
		}

		return 0;
	}

	private function row(string $label, string $key, mixed $value): array {
		return [
			'label' => $label,
			'key' => $key,
			'value' => $this->formatValue($value),
		];
	}

	private function value(array $row, string $key): string {
		if (!array_key_exists($key, $row)) {
			return '';
		}

		return $this->formatValue($row[$key]);
	}

	private function serverValue(string $key): string {
		$value = filter_input(INPUT_SERVER, $key);

		if ($value !== null && $value !== false) {
			return (string)$value;
		}

		if (is_array($_SERVER ?? null) && array_key_exists($key, $_SERVER)) {
			return $this->formatValue($_SERVER[$key]);
		}

		return '';
	}

	private function formatValue(mixed $value): string {
		if ($value === null) {
			return '';
		}

		if (is_bool($value)) {
			return $value ? 'yes' : 'no';
		}

		if (is_scalar($value)) {
			return (string)$value;
		}

		if (is_array($value)) {
			if (empty($value)) {
				return '';
			}

			return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
		}

		return get_debug_type($value);
	}
}
