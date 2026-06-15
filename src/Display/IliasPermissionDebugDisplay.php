<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use ilObject;
use ilObjUser;
use ilRbacReview;

final class IliasPermissionDebugDisplay implements IDisplay {

	private const PARAM_TARGET_REF_ID = 'base3_target_ref_id';
	private const PARAM_USER_ID = 'base3_user_id';

	private const DEFAULT_OPERATIONS = [
		'visible',
		'read',
		'write',
		'edit_permission',
		'delete',
		'copy',
	];

	public function __construct(
		private readonly IMvcView $view,
		private readonly ilObjUser $ilUser,
		private readonly ilRbacReview $rbacreview
	) {}

	public static function getName(): string {
		return 'iliaspermissiondebugdisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		return 'ILIAS RBAC permission debug overview.';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$userId = $this->getRequestUserId();
		$targetRefId = $this->getRequestTargetRefId();

		$operationMap = $this->getOperationMap();
		$assignedRoleIds = $this->rbacreview->assignedRoles($userId);
		$globalRoleIds = $this->rbacreview->assignedGlobalRoles($userId);
		$parentRoles = $this->getParentRoles($targetRefId);
		$relevantRoleIds = $this->getRelevantRoleIds($assignedRoleIds, $parentRoles);
		$roleOperationMap = $this->getRoleOperationMap($targetRefId, $relevantRoleIds);

		$this->view->setPath(\DIR_COMPONENTS . 'Base3/Base3Ilias');
		$this->view->setTemplate('Display/IliasPermissionDebugDisplay.php');

		$this->view->assign('generatedAt', date('c'));
		$this->view->assign('targetRefId', $targetRefId);
		$this->view->assign('userId', $userId);
		$this->view->assign('currentUserId', $this->getCurrentUserId());
		$this->view->assign('targetParamName', self::PARAM_TARGET_REF_ID);
		$this->view->assign('userParamName', self::PARAM_USER_ID);
		$this->view->assign('targetRows', $this->getTargetRows($targetRefId));
		$this->view->assign('userRows', $this->getUserRows($userId));
		$this->view->assign('assignedRoleRows', $this->getAssignedRoleRows($assignedRoleIds, $globalRoleIds));
		$this->view->assign('parentRoleRows', $this->getParentRoleRows($parentRoles, $assignedRoleIds));
		$this->view->assign('effectiveRows', $this->getEffectiveRows($relevantRoleIds, $roleOperationMap));
		$this->view->assign('rolePermissionRows', $this->getRolePermissionRows($parentRoles, $relevantRoleIds, $roleOperationMap, $operationMap));

		return $this->view->loadTemplate();
	}

	private function getTargetRows(int $targetRefId): array {
		if ($targetRefId <= 0) {
			return [
				$this->row('Target Ref ID', self::PARAM_TARGET_REF_ID, ''),
				$this->row('Status', 'target', 'No target ref_id given. Use ' . self::PARAM_TARGET_REF_ID . ' to inspect object permissions.'),
			];
		}

		$objId = ilObject::_lookupObjId($targetRefId);
		$type = ilObject::_lookupType($targetRefId, true);
		$title = $objId > 0 ? ilObject::_lookupTitle($objId) : '';

		return [
			$this->row('Target Ref ID', self::PARAM_TARGET_REF_ID, $targetRefId),
			$this->row('Object ID', 'obj_id', $objId),
			$this->row('Object Type', 'type', $type),
			$this->row('Title', 'title', $title),
		];
	}

	private function getUserRows(int $userId): array {
		$name = ilObjUser::_lookupName($userId);

		return [
			$this->row('Selected User ID', self::PARAM_USER_ID, $userId),
			$this->row('Current Session User ID', 'ilObjUser::getId()', $this->getCurrentUserId()),
			$this->row('Login', 'ilObjUser::_lookupLogin()', ilObjUser::_lookupLogin($userId)),
			$this->row('Firstname', 'ilObjUser::_lookupName()[firstname]', (string)($name['firstname'] ?? '')),
			$this->row('Lastname', 'ilObjUser::_lookupName()[lastname]', (string)($name['lastname'] ?? '')),
			$this->row('Email', 'ilObjUser::_lookupEmail()', ilObjUser::_lookupEmail($userId)),
			$this->row('Exists', 'ilObjUser::_lookupName()[user_id]', ((int)($name['user_id'] ?? 0)) > 0 ? 'yes' : 'no'),
		];
	}

	private function getAssignedRoleRows(array $assignedRoleIds, array $globalRoleIds): array {
		$assignedRoleIds = array_map('intval', $assignedRoleIds);
		$globalRoleIds = array_map('intval', $globalRoleIds);

		sort($assignedRoleIds);
		sort($globalRoleIds);

		$rows = [];

		foreach ($assignedRoleIds as $roleId) {
			$rows[] = [
				'role_id' => $roleId,
				'title' => ilObject::_lookupTitle($roleId),
				'type' => in_array($roleId, $globalRoleIds, true) ? 'global' : 'local / linked',
			];
		}

		return $rows;
	}

	private function getParentRoleRows(array $parentRoles, array $assignedRoleIds): array {
		$assignedRoleIds = array_map('intval', $assignedRoleIds);
		$rows = [];

		foreach ($parentRoles as $roleId => $role) {
			$roleId = (int)$roleId;

			$rows[] = [
				'role_id' => $roleId,
				'title' => (string)($role['title'] ?? ilObject::_lookupTitle($roleId)),
				'type' => (string)($role['role_type'] ?? ''),
				'parent' => (string)($role['parent'] ?? ''),
				'assigned' => in_array($roleId, $assignedRoleIds, true),
				'protected' => !empty($role['protected']),
			];
		}

		usort($rows, static fn(array $a, array $b): int => $a['role_id'] <=> $b['role_id']);

		return $rows;
	}

	private function getEffectiveRows(array $relevantRoleIds, array $roleOperationMap): array {
		$rows = [];

		foreach (self::DEFAULT_OPERATIONS as $operation) {
			$operationId = ilRbacReview::_getOperationIdByName($operation);
			$grantedBy = [];

			if ($operationId > 0) {
				foreach ($relevantRoleIds as $roleId) {
					$roleId = (int)$roleId;
					$operations = $roleOperationMap[$roleId] ?? [];

					if (in_array($operationId, $operations, true)) {
						$grantedBy[] = $roleId . ' ' . ilObject::_lookupTitle($roleId);
					}
				}
			}

			$rows[] = [
				'operation' => $operation,
				'operation_id' => $operationId > 0 ? (string)$operationId : '',
				'granted' => !empty($grantedBy),
				'granted_by' => implode("\n", $grantedBy),
			];
		}

		return $rows;
	}

	private function getRolePermissionRows(array $parentRoles, array $relevantRoleIds, array $roleOperationMap, array $operationMap): array {
		$rows = [];

		foreach ($relevantRoleIds as $roleId) {
			$roleId = (int)$roleId;
			$role = $parentRoles[$roleId] ?? [];
			$operationIds = $roleOperationMap[$roleId] ?? [];

			$rows[] = [
				'role_id' => $roleId,
				'title' => (string)($role['title'] ?? ilObject::_lookupTitle($roleId)),
				'type' => (string)($role['role_type'] ?? ''),
				'parent' => (string)($role['parent'] ?? ''),
				'operations' => $this->formatOperations($operationIds, $operationMap),
			];
		}

		usort($rows, static fn(array $a, array $b): int => $a['role_id'] <=> $b['role_id']);

		return $rows;
	}

	private function getParentRoles(int $targetRefId): array {
		if ($targetRefId <= 0) {
			return [];
		}

		return $this->rbacreview->getParentRoleIds($targetRefId, false);
	}

	private function getRelevantRoleIds(array $assignedRoleIds, array $parentRoles): array {
		$parentRoleIds = array_map('intval', array_keys($parentRoles));
		$assignedRoleIds = array_map('intval', $assignedRoleIds);

		$roleIds = array_values(array_intersect($assignedRoleIds, $parentRoleIds));
		sort($roleIds);

		return $roleIds;
	}

	private function getRoleOperationMap(int $targetRefId, array $roleIds): array {
		if ($targetRefId <= 0) {
			return [];
		}

		$out = [];

		foreach ($roleIds as $roleId) {
			$out[(int)$roleId] = array_map(
				'intval',
				$this->rbacreview->getRoleOperationsOnObject((int)$roleId, $targetRefId)
			);
		}

		return $out;
	}

	private function getOperationMap(): array {
		$out = [];

		foreach ($this->rbacreview->getOperations() as $operation) {
			$operationId = (int)($operation['ops_id'] ?? 0);

			if ($operationId <= 0) {
				continue;
			}

			$out[$operationId] = [
				'id' => $operationId,
				'name' => (string)($operation['operation'] ?? ''),
				'description' => (string)($operation['description'] ?? ''),
			];
		}

		return $out;
	}

	private function formatOperations(array $operationIds, array $operationMap): string {
		if (empty($operationIds)) {
			return '';
		}

		$operationIds = array_map('intval', $operationIds);
		sort($operationIds);

		$out = [];

		foreach ($operationIds as $operationId) {
			$name = (string)($operationMap[$operationId]['name'] ?? '');

			if ($name === '') {
				$out[] = (string)$operationId;
				continue;
			}

			$out[] = $operationId . ' ' . $name;
		}

		return implode("\n", $out);
	}

	private function getRequestTargetRefId(): int {
		$params = $this->getQueryParams();

		if (isset($params[self::PARAM_TARGET_REF_ID]) && is_numeric($params[self::PARAM_TARGET_REF_ID])) {
			return (int)$params[self::PARAM_TARGET_REF_ID];
		}

		return 0;
	}

	private function getRequestUserId(): int {
		$params = $this->getQueryParams();

		if (isset($params[self::PARAM_USER_ID]) && is_numeric($params[self::PARAM_USER_ID]) && (int)$params[self::PARAM_USER_ID] > 0) {
			return (int)$params[self::PARAM_USER_ID];
		}

		return $this->getCurrentUserId();
	}

	private function getQueryParams(): array {
		$params = [];
		parse_str($this->serverValue('QUERY_STRING'), $params);

		return $params;
	}

	private function getCurrentUserId(): int {
		return (int)$this->ilUser->getId();
	}

	private function row(string $label, string $key, mixed $value): array {
		return [
			'label' => $label,
			'key' => $key,
			'value' => $this->formatValue($value),
		];
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
