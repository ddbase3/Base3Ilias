<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Group;
use Base3Ilias\Base3\Base3IliasUsermanager;
use ilObject;
use ilObjUser;
use ilRbacReview;

final class Base3IliasGroupAdminDisplay implements IDisplay {

	private const ACTION_SAVE = 'save';

	private array $translations = [];

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly ISettingsStore $settingsStore,
		private readonly IUsermanager $usermanager,
		private readonly ilRbacReview $rbacreview,
		private readonly ILinkTargetService $linkTargetService
	) {}

	public static function getName(): string {
		return 'base3iliasgroupadmindisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		$this->loadTranslations();

		return $this->t('help', 'Maps stable BASE3 groups to ILIAS roles and users.');
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->loadTranslations();
		$message = '';

		if ((string)$this->request->post('base3_group_action', '') === self::ACTION_SAVE) {
			$this->saveSettings();
			$message = $this->t('saved', 'Group assignments saved.');
		}

		$settings = $this->settingsStore->get(
			Base3IliasUsermanager::SETTINGS_GROUP,
			Base3IliasUsermanager::SETTINGS_NAME,
			array()
		);

		$this->view->setTemplate('Display/Base3IliasGroupAdminDisplay.php');
		$this->view->assign('groups', $this->getGroupRows($settings));
		$this->view->assign('roleOptions', $this->getRoleOptions());
		$this->view->assign('defaultGroup', trim((string)($settings['default_group'] ?? '')));
		$this->view->assign('message', $message);
		$this->view->assign('endpoint', $this->buildEndpoint());
		$this->view->assign('translations', $this->translations);

		return $this->view->loadTemplate();
	}

	private function saveSettings(): void {
		$roleAssignments = $this->request->post('base3_group_roles', array());
		$userAssignments = $this->request->post('base3_group_user_ids', array());
		$roleAssignments = is_array($roleAssignments) ? $roleAssignments : array();
		$userAssignments = is_array($userAssignments) ? $userAssignments : array();

		$assignments = array();
		$configurableGroupNames = $this->getConfigurableGroupNames();

		foreach ($configurableGroupNames as $groupName) {
			$assignments[$groupName] = array(
				'role_ids' => $this->normalizeIds($roleAssignments[$groupName] ?? array()),
				'user_ids' => $this->normalizeIds($userAssignments[$groupName] ?? '')
			);
		}

		$defaultGroup = trim((string)$this->request->post('base3_default_group', ''));
		if (!in_array($defaultGroup, $configurableGroupNames, true)) {
			$defaultGroup = '';
		}

		$this->settingsStore->set(
			Base3IliasUsermanager::SETTINGS_GROUP,
			Base3IliasUsermanager::SETTINGS_NAME,
			array(
				'assignments' => $assignments,
				'default_group' => $defaultGroup
			)
		);
		$this->settingsStore->save();
	}

	private function getGroupRows(array $settings): array {
		$assignments = is_array($settings['assignments'] ?? null) ? $settings['assignments'] : array();
		$rows = array();

		foreach ($this->usermanager->getAllGroups() as $group) {
			if (!$group instanceof Group) continue;

			$groupName = trim((string)$group->name);
			if ($groupName === '') continue;

			$automatic = $this->isAutomaticGroup($groupName);
			$assignment = is_array($assignments[$groupName] ?? null) ? $assignments[$groupName] : array();
			$userIds = $automatic ? array() : $this->normalizeIds($assignment['user_ids'] ?? array());

			$rows[] = array(
				'name' => $groupName,
				'info' => trim((string)$group->info),
				'automatic' => $automatic,
				'role_ids' => $automatic ? array() : $this->normalizeIds($assignment['role_ids'] ?? array()),
				'user_ids' => $userIds,
				'user_id_value' => implode(', ', $userIds),
				'users' => $this->getUserLabels($userIds)
			);
		}

		return $rows;
	}

	private function getRoleOptions(): array {
		$roleIds = array_map('intval', $this->rbacreview->getGlobalRoles());
		$roleIds = array_values(array_unique(array_filter($roleIds, static fn(int $roleId): bool => $roleId > 0)));
		sort($roleIds);

		$options = array();
		foreach ($roleIds as $roleId) {
			$options[] = array(
				'id' => $roleId,
				'label' => trim((string)ilObject::_lookupTitle($roleId))
			);
		}

		return $options;
	}

	private function getConfigurableGroupNames(): array {
		$names = array();

		foreach ($this->usermanager->getAllGroups() as $group) {
			if (!$group instanceof Group) continue;

			$name = trim((string)$group->name);
			if ($name === '' || $this->isAutomaticGroup($name)) continue;

			$names[] = $name;
		}

		return array_values(array_unique($names));
	}

	private function isAutomaticGroup(string $groupName): bool {
		return in_array($groupName, [
			Base3IliasUsermanager::GROUP_ANONYMOUS,
			Base3IliasUsermanager::GROUP_AUTHENTICATED
		], true);
	}

	private function normalizeIds(mixed $value): array {
		$values = is_array($value)
			? $value
			: preg_split('/[\s,;]+/', trim((string)$value), -1, PREG_SPLIT_NO_EMPTY);

		if (!is_array($values)) return array();

		$ids = array();
		foreach ($values as $id) {
			if (!is_numeric($id) || (int)$id <= 0) continue;
			$ids[] = (int)$id;
		}

		$ids = array_values(array_unique($ids));
		sort($ids);

		return $ids;
	}

	private function getUserLabels(array $userIds): array {
		$users = array();

		foreach ($userIds as $userId) {
			$login = trim((string)ilObjUser::_lookupLogin($userId));
			$fullname = trim((string)ilObjUser::_lookupFullname($userId));
			$label = $login;

			if ($fullname !== '') {
				$label = $label !== '' ? $label . ' · ' . $fullname : $fullname;
			}

			$users[] = array(
				'id' => $userId,
				'label' => $label
			);
		}

		return $users;
	}

	private function buildEndpoint(): string {
		return $this->linkTargetService->getLink([
			'name' => self::getName(),
			'out' => 'html'
		]);
	}

	private function loadTranslations(): void {
		$this->view->setPath(\DIR_BASE3 . 'Base3Ilias');
		$this->view->loadBricks('Display');

		$common = $this->view->getBricks('base3ilias_common');
		$specific = $this->view->getBricks('base3ilias_group_admin_display');

		$this->translations = array_merge(
			is_array($common) ? $common : [],
			is_array($specific) ? $specific : []
		);
	}

	private function t(string $key, string $fallback): string {
		$text = trim((string)($this->translations[$key] ?? ''));
		return $text !== '' ? $text : $fallback;
	}
}
