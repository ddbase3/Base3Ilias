<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use ilObject;
use ilObjUser;
use ilRbacReview;

final class IliasUserDebugDisplay implements IDisplay {

	private const PARAM_USER_ID = 'base3_user_id';
	private const PARAM_USER_LOGIN = 'base3_user_login';

	private const PREF_KEYS = [
		'language',
		'user_tz',
		'skin',
		'style',
		'date_format',
		'time_format',
		'hits_per_page',
		'session_reminder_enabled',
		'session_reminder_lead_time',
		'hide_own_online_status',
		'delete_flag',
	];

	public function __construct(
		private readonly IMvcView $view,
		private readonly ilObjUser $ilUser,
		private readonly ilRbacReview $rbacreview
	) {}

	public static function getName(): string {
		return 'iliasuserdebugdisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		return 'ILIAS user debug overview.';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$selection = $this->getUserSelection();
		$userId = (int)$selection['user_id'];

		$this->view->setPath(\DIR_COMPONENTS . 'Base3/Base3Ilias');
		$this->view->setTemplate('Display/IliasUserDebugDisplay.php');

		$this->view->assign('generatedAt', date('c'));
		$this->view->assign('userIdParamName', self::PARAM_USER_ID);
		$this->view->assign('userLoginParamName', self::PARAM_USER_LOGIN);
		$this->view->assign('currentUserId', $this->getCurrentUserId());
		$this->view->assign('selectedUserId', $userId);
		$this->view->assign('selectedLogin', (string)$selection['login']);
		$this->view->assign('selectionMessage', (string)$selection['message']);
		$this->view->assign('userRows', $this->getUserRows($userId));
		$this->view->assign('statusRows', $this->getStatusRows($userId));
		$this->view->assign('preferenceRows', $this->getPreferenceRows($userId));
		$this->view->assign('roleRows', $this->getRoleRows($userId));
		$this->view->assign('globalRoleRows', $this->getGlobalRoleRows($userId));

		return $this->view->loadTemplate();
	}

	private function getUserSelection(): array {
		$params = $this->getQueryParams();

		$login = trim((string)($params[self::PARAM_USER_LOGIN] ?? ''));

		if ($login !== '') {
			$lookupId = ilObjUser::_lookupId($login);
			$userId = is_numeric($lookupId) ? (int)$lookupId : 0;

			return [
				'user_id' => $userId,
				'login' => $login,
				'message' => $userId > 0 ? '' : 'No user found for login "' . $login . '".',
			];
		}

		if (isset($params[self::PARAM_USER_ID]) && is_numeric($params[self::PARAM_USER_ID]) && (int)$params[self::PARAM_USER_ID] > 0) {
			return [
				'user_id' => (int)$params[self::PARAM_USER_ID],
				'login' => '',
				'message' => '',
			];
		}

		return [
			'user_id' => $this->getCurrentUserId(),
			'login' => '',
			'message' => '',
		];
	}

	private function getUserRows(int $userId): array {
		if ($userId <= 0) {
			return [
				$this->row('Selected User ID', self::PARAM_USER_ID, ''),
				$this->row('Status', 'user', 'No user selected.'),
			];
		}

		$name = ilObjUser::_lookupName($userId);
		$exists = ((int)($name['user_id'] ?? 0)) > 0;

		if (!$exists) {
			return [
				$this->row('Selected User ID', self::PARAM_USER_ID, $userId),
				$this->row('Exists', 'ilObjUser::_lookupName()[user_id]', 'no'),
			];
		}

		return [
			$this->row('Selected User ID', self::PARAM_USER_ID, $userId),
			$this->row('Current Session User ID', 'ilObjUser::getId()', $this->getCurrentUserId()),
			$this->row('Login', 'ilObjUser::_lookupLogin()', ilObjUser::_lookupLogin($userId)),
			$this->row('Title', 'ilObjUser::_lookupName()[title]', (string)($name['title'] ?? '')),
			$this->row('Firstname', 'ilObjUser::_lookupName()[firstname]', (string)($name['firstname'] ?? '')),
			$this->row('Lastname', 'ilObjUser::_lookupName()[lastname]', (string)($name['lastname'] ?? '')),
			$this->row('Fullname', 'ilObjUser::_lookupFullname()', ilObjUser::_lookupFullname($userId)),
			$this->row('Email', 'ilObjUser::_lookupEmail()', ilObjUser::_lookupEmail($userId)),
			$this->row('Language', 'ilObjUser::_lookupLanguage()', ilObjUser::_lookupLanguage($userId)),
			$this->row('Exists', 'ilObjUser::_lookupName()[user_id]', 'yes'),
		];
	}

	private function getStatusRows(int $userId): array {
		if (!$this->userExists($userId)) {
			return [];
		}

		$profile = $this->getProfileData($userId);

		return [
			$this->row('Active', 'ilObjUser::_lookupActive()', ilObjUser::_lookupActive($userId) ? 'yes' : 'no'),
			$this->row('Authentication Mode', 'ilObjUser::_lookupAuthMode()', ilObjUser::_lookupAuthMode($userId)),
			$this->row('External Account', 'ilObjUser::_lookupExternalAccount()', ilObjUser::_lookupExternalAccount($userId)),
			$this->row('First Login', 'ilObjUser::_lookupFirstLogin()', ilObjUser::_lookupFirstLogin($userId)),
			$this->row('Last Login', 'ilObjUser::_lookupLastLogin()', ilObjUser::_lookupLastLogin($userId)),
			$this->row('Created', 'usr_data.create_date', $this->profileValue($profile, 'create_date')),
			$this->row('Last Update', 'usr_data.last_update', $this->profileValue($profile, 'last_update')),
			$this->row('Approve Date', 'usr_data.approve_date', $this->profileValue($profile, 'approve_date')),
			$this->row('Agreement Date', 'usr_data.agree_date', $this->profileValue($profile, 'agree_date')),
			$this->row('Inactivation Date', 'usr_data.inactivation_date', $this->profileValue($profile, 'inactivation_date')),
			$this->row('Login Attempts', 'usr_data.login_attempts', $this->profileValue($profile, 'login_attempts')),
			$this->row('Password Policy Reset', 'usr_data.passwd_policy_reset', $this->boolProfileValue($profile, 'passwd_policy_reset')),
			$this->row('Profile Incomplete', 'usr_data.profile_incomplete', $this->boolProfileValue($profile, 'profile_incomplete')),
			$this->row('Self Registered', 'usr_data.is_self_registered', $this->boolProfileValue($profile, 'is_self_registered')),
			$this->row('Time Limit Unlimited', 'usr_data.time_limit_unlimited', $this->boolProfileValue($profile, 'time_limit_unlimited')),
			$this->row('Time Limit From', 'usr_data.time_limit_from', $this->timestampProfileValue($profile, 'time_limit_from')),
			$this->row('Time Limit Until', 'usr_data.time_limit_until', $this->timestampProfileValue($profile, 'time_limit_until')),
			$this->row('Time Limit Owner', 'usr_data.time_limit_owner', $this->profileValue($profile, 'time_limit_owner')),
		];
	}

	private function getPreferenceRows(int $userId): array {
		if (!$this->userExists($userId)) {
			return [];
		}

		$rows = [];

		foreach (self::PREF_KEYS as $key) {
			$rows[] = [
				'key' => $key,
				'value' => $this->formatValue(ilObjUser::_lookupPref($userId, $key)),
			];
		}

		return $rows;
	}

	private function getRoleRows(int $userId): array {
		if (!$this->userExists($userId)) {
			return [];
		}

		$roleIds = array_map('intval', $this->rbacreview->assignedRoles($userId));
		$globalRoleIds = array_map('intval', $this->rbacreview->assignedGlobalRoles($userId));

		sort($roleIds);
		sort($globalRoleIds);

		$rows = [];

		foreach ($roleIds as $roleId) {
			$rows[] = [
				'role_id' => $roleId,
				'title' => ilObject::_lookupTitle($roleId),
				'type' => in_array($roleId, $globalRoleIds, true) ? 'global' : 'local / linked',
			];
		}

		return $rows;
	}

	private function getGlobalRoleRows(int $userId): array {
		if (!$this->userExists($userId)) {
			return [];
		}

		$roleIds = array_map('intval', $this->rbacreview->assignedGlobalRoles($userId));
		sort($roleIds);

		$rows = [];

		foreach ($roleIds as $roleId) {
			$rows[] = [
				'role_id' => $roleId,
				'title' => ilObject::_lookupTitle($roleId),
			];
		}

		return $rows;
	}

	private function getProfileData(int $userId): array {
		if ($userId <= 0) {
			return [];
		}

		$rows = ilObjUser::_readUsersProfileData([$userId]);

		if (!isset($rows[$userId]) || !is_array($rows[$userId])) {
			return [];
		}

		return $rows[$userId];
	}

	private function userExists(int $userId): bool {
		if ($userId <= 0) {
			return false;
		}

		$name = ilObjUser::_lookupName($userId);

		return ((int)($name['user_id'] ?? 0)) > 0;
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

	private function profileValue(array $profile, string $key): string {
		if (!array_key_exists($key, $profile)) {
			return '';
		}

		return $this->formatValue($profile[$key]);
	}

	private function boolProfileValue(array $profile, string $key): string {
		if (!array_key_exists($key, $profile) || $profile[$key] === '') {
			return '';
		}

		return ((int)$profile[$key]) > 0 ? 'yes' : 'no';
	}

	private function timestampProfileValue(array $profile, string $key): string {
		if (!array_key_exists($key, $profile) || $profile[$key] === '' || $profile[$key] === null) {
			return '';
		}

		$value = (int)$profile[$key];

		if ($value <= 0) {
			return '';
		}

		return $value . ' (' . date('Y-m-d H:i:s', $value) . ')';
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
