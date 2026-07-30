<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use ilObject;
use ilObjUser;
use ilRbacReview;

final class IliasPermissionDebugDisplay implements IDisplay {

	private array $translations = [];

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
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly ILinkTargetService $linkTargetService,
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
		$this->loadTranslations();

		return $this->t('help', 'ILIAS RBAC permission debug overview.');
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->loadTranslations();
		$userId = $this->getRequestUserId();
		$targetRefId = $this->getRequestTargetRefId();

		$operationMap = $this->getOperationMap();
		$assignedRoleIds = $this->rbacreview->assignedRoles($userId);
		$globalRoleIds = $this->rbacreview->assignedGlobalRoles($userId);
		$parentRoles = $this->getParentRoles($targetRefId);
		$relevantRoleIds = $this->getRelevantRoleIds($assignedRoleIds, $parentRoles);
		$roleOperationMap = $this->getRoleOperationMap($targetRefId, $relevantRoleIds);

		$this->view->setTemplate('Display/IliasPermissionDebugDisplay.php');

		$this->view->assign('generatedAt', date('c'));
		$this->view->assign('targetRefId', $targetRefId);
		$this->view->assign('userId', $userId);
		$this->view->assign('currentUserId', $this->getCurrentUserId());
		$this->view->assign('targetParamName', self::PARAM_TARGET_REF_ID);
		$this->view->assign('userParamName', self::PARAM_USER_ID);
		$this->view->assign('endpoint', $this->buildEndpoint());
		$this->view->assign('targetRows', $this->getTargetRows($targetRefId));
		$this->view->assign('userRows', $this->getUserRows($userId));
		$this->view->assign('assignedRoleRows', $this->getAssignedRoleRows($assignedRoleIds, $globalRoleIds));
		$this->view->assign('parentRoleRows', $this->getParentRoleRows($parentRoles, $assignedRoleIds));
		$this->view->assign('effectiveRows', $this->getEffectiveRows($relevantRoleIds, $roleOperationMap));
		$this->view->assign('rolePermissionRows', $this->getRolePermissionRows($parentRoles, $relevantRoleIds, $roleOperationMap, $operationMap));
		$this->view->assign('translations', $this->translations);

		return $this->view->loadTemplate();
	}

	private function getTargetRows(int $targetRefId): array {
		if ($targetRefId <= 0) {
			return [
				$this->row($this->t('target_ref_id', 'Target ref_id'), self::PARAM_TARGET_REF_ID, ''),
				$this->row($this->t('column_status', 'Status'), 'target', $this->t('no_target', 'No target ref_id given. Use %s to inspect object permissions.', self::PARAM_TARGET_REF_ID)),
			];
		}

		$objId = ilObject::_lookupObjId($targetRefId);
		$type = ilObject::_lookupType($targetRefId, true);
		$title = $objId > 0 ? ilObject::_lookupTitle($objId) : '';

		return [
			$this->row($this->t('target_ref_id', 'Target ref_id'), self::PARAM_TARGET_REF_ID, $targetRefId),
			$this->row($this->t('object_id', 'Object ID'), 'obj_id', $objId),
			$this->row($this->t('object_type', 'Object type'), 'type', $type),
			$this->row($this->t('column_title', 'Title'), 'title', $title),
		];
	}

	private function getUserRows(int $userId): array {
		$name = ilObjUser::_lookupName($userId);

		return [
			$this->row($this->t('selected_user_id', 'Selected user ID'), self::PARAM_USER_ID, $userId),
			$this->row($this->t('current_session_user_id', 'Current session user ID'), 'ilObjUser::getId()', $this->getCurrentUserId()),
			$this->row($this->t('login', 'Login'), 'ilObjUser::_lookupLogin()', ilObjUser::_lookupLogin($userId)),
			$this->row($this->t('first_name', 'First name'), 'ilObjUser::_lookupName()[firstname]', (string)($name['firstname'] ?? '')),
			$this->row($this->t('last_name', 'Last name'), 'ilObjUser::_lookupName()[lastname]', (string)($name['lastname'] ?? '')),
			$this->row($this->t('email', 'Email'), 'ilObjUser::_lookupEmail()', ilObjUser::_lookupEmail($userId)),
			$this->row($this->t('exists', 'Exists'), 'ilObjUser::_lookupName()[user_id]', ((int)($name['user_id'] ?? 0)) > 0 ? $this->t('value_yes', 'yes') : $this->t('value_no', 'no')),
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
				'type' => in_array($roleId, $globalRoleIds, true) ? $this->t('role_type_global', 'global') : $this->t('role_type_local_linked', 'local / linked'),
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
		$value = $this->request->request(self::PARAM_TARGET_REF_ID);

		if (is_numeric($value) && (int)$value > 0) {
			return (int)$value;
		}

		return 0;
	}

	private function getRequestUserId(): int {
		$value = $this->request->request(self::PARAM_USER_ID);

		if (is_numeric($value) && (int)$value > 0) {
			return (int)$value;
		}

		return $this->getCurrentUserId();
	}

	private function buildEndpoint(): string {
		return $this->linkTargetService->getLink([
			'name' => self::getName(),
			'out' => 'html',
		]);
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

	private function formatValue(mixed $value): string {
		if ($value === null) {
			return '';
		}

		if (is_bool($value)) {
			return $value ? $this->t('value_yes', 'yes') : $this->t('value_no', 'no');
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

	private function loadTranslations(): void {
		$this->view->setPath(\DIR_COMPONENTS . 'Base3/Base3Ilias');
		$this->view->loadBricks('Display');

		$common = $this->view->getBricks('base3ilias_common');
		$specific = $this->view->getBricks('ilias_permission_debug_display');

		$this->translations = array_merge(
			is_array($common) ? $common : [],
			is_array($specific) ? $specific : []
		);
	}

	private function t(string $key, string $fallback, mixed ...$values): string {
		$text = trim((string)($this->translations[$key] ?? ''));
		if ($text === '') {
			$text = $fallback;
		}

		return $values === [] ? $text : vsprintf($text, $values);
	}
}
