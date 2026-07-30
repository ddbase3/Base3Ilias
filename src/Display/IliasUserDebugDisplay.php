<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use ilObject;
use ilObjUser;
use ilRbacReview;

final class IliasUserDebugDisplay implements IDisplay {

	private array $translations = [];

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
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly ILinkTargetService $linkTargetService,
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
		$this->loadTranslations();

		return $this->t('help', 'ILIAS user debug overview.');
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->loadTranslations();
		$selection = $this->getUserSelection();
		$userId = (int)$selection['user_id'];

		$this->view->setTemplate('Display/IliasUserDebugDisplay.php');

		$this->view->assign('generatedAt', date('c'));
		$this->view->assign('userIdParamName', self::PARAM_USER_ID);
		$this->view->assign('userLoginParamName', self::PARAM_USER_LOGIN);
		$this->view->assign('currentUserId', $this->getCurrentUserId());
		$this->view->assign('endpoint', $this->buildEndpoint());
		$this->view->assign('selectedUserId', $userId);
		$this->view->assign('selectedLogin', (string)$selection['login']);
		$this->view->assign('selectionMessage', (string)$selection['message']);
		$this->view->assign('userRows', $this->getUserRows($userId));
		$this->view->assign('statusRows', $this->getStatusRows($userId));
		$this->view->assign('preferenceRows', $this->getPreferenceRows($userId));
		$this->view->assign('roleRows', $this->getRoleRows($userId));
		$this->view->assign('globalRoleRows', $this->getGlobalRoleRows($userId));
		$this->view->assign('translations', $this->translations);

		return $this->view->loadTemplate();
	}

	private function getUserSelection(): array {
		$login = trim((string)$this->request->request(self::PARAM_USER_LOGIN, ''));

		if ($login !== '') {
			$lookupId = ilObjUser::_lookupId($login);
			$userId = is_numeric($lookupId) ? (int)$lookupId : 0;

			return [
				'user_id' => $userId,
				'login' => $login,
				'message' => $userId > 0 ? '' : $this->t('no_user_for_login', 'No user found for login "%s".', $login),
			];
		}

		$userId = $this->request->request(self::PARAM_USER_ID);
		if (is_numeric($userId) && (int)$userId > 0) {
			return [
				'user_id' => (int)$userId,
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
				$this->row($this->t('selected_user_id', 'Selected user ID'), self::PARAM_USER_ID, ''),
				$this->row($this->t('column_status', 'Status'), 'user', $this->t('no_user_selected', 'No user selected.')),
			];
		}

		$name = ilObjUser::_lookupName($userId);
		$exists = ((int)($name['user_id'] ?? 0)) > 0;

		if (!$exists) {
			return [
				$this->row($this->t('selected_user_id', 'Selected user ID'), self::PARAM_USER_ID, $userId),
				$this->row($this->t('exists', 'Exists'), 'ilObjUser::_lookupName()[user_id]', $this->t('value_no', 'no')),
			];
		}

		return [
			$this->row($this->t('selected_user_id', 'Selected user ID'), self::PARAM_USER_ID, $userId),
			$this->row($this->t('current_session_user_id', 'Current session user ID'), 'ilObjUser::getId()', $this->getCurrentUserId()),
			$this->row($this->t('login', 'Login'), 'ilObjUser::_lookupLogin()', ilObjUser::_lookupLogin($userId)),
			$this->row($this->t('column_title', 'Title'), 'ilObjUser::_lookupName()[title]', (string)($name['title'] ?? '')),
			$this->row($this->t('first_name', 'First name'), 'ilObjUser::_lookupName()[firstname]', (string)($name['firstname'] ?? '')),
			$this->row($this->t('last_name', 'Last name'), 'ilObjUser::_lookupName()[lastname]', (string)($name['lastname'] ?? '')),
			$this->row($this->t('full_name', 'Full name'), 'ilObjUser::_lookupFullname()', ilObjUser::_lookupFullname($userId)),
			$this->row($this->t('email', 'Email'), 'ilObjUser::_lookupEmail()', ilObjUser::_lookupEmail($userId)),
			$this->row($this->t('language', 'Language'), 'ilObjUser::_lookupLanguage()', ilObjUser::_lookupLanguage($userId)),
			$this->row($this->t('exists', 'Exists'), 'ilObjUser::_lookupName()[user_id]', $this->t('value_yes', 'yes')),
		];
	}

	private function getStatusRows(int $userId): array {
		if (!$this->userExists($userId)) {
			return [];
		}

		$profile = $this->getProfileData($userId);

		return [
			$this->row($this->t('active', 'Active'), 'ilObjUser::_lookupActive()', ilObjUser::_lookupActive($userId) ? $this->t('value_yes', 'yes') : $this->t('value_no', 'no')),
			$this->row($this->t('authentication_mode', 'Authentication mode'), 'ilObjUser::_lookupAuthMode()', ilObjUser::_lookupAuthMode($userId)),
			$this->row($this->t('external_account', 'External account'), 'ilObjUser::_lookupExternalAccount()', ilObjUser::_lookupExternalAccount($userId)),
			$this->row($this->t('first_login', 'First login'), 'ilObjUser::_lookupFirstLogin()', ilObjUser::_lookupFirstLogin($userId)),
			$this->row($this->t('last_login', 'Last login'), 'ilObjUser::_lookupLastLogin()', ilObjUser::_lookupLastLogin($userId)),
			$this->row($this->t('created', 'Created'), 'usr_data.create_date', $this->profileValue($profile, 'create_date')),
			$this->row($this->t('last_update', 'Last update'), 'usr_data.last_update', $this->profileValue($profile, 'last_update')),
			$this->row($this->t('approve_date', 'Approval date'), 'usr_data.approve_date', $this->profileValue($profile, 'approve_date')),
			$this->row($this->t('agreement_date', 'Agreement date'), 'usr_data.agree_date', $this->profileValue($profile, 'agree_date')),
			$this->row($this->t('inactivation_date', 'Inactivation date'), 'usr_data.inactivation_date', $this->profileValue($profile, 'inactivation_date')),
			$this->row($this->t('login_attempts', 'Login attempts'), 'usr_data.login_attempts', $this->profileValue($profile, 'login_attempts')),
			$this->row($this->t('password_policy_reset', 'Password policy reset'), 'usr_data.passwd_policy_reset', $this->boolProfileValue($profile, 'passwd_policy_reset')),
			$this->row($this->t('profile_incomplete', 'Profile incomplete'), 'usr_data.profile_incomplete', $this->boolProfileValue($profile, 'profile_incomplete')),
			$this->row($this->t('self_registered', 'Self-registered'), 'usr_data.is_self_registered', $this->boolProfileValue($profile, 'is_self_registered')),
			$this->row($this->t('time_limit_unlimited', 'Unlimited time limit'), 'usr_data.time_limit_unlimited', $this->boolProfileValue($profile, 'time_limit_unlimited')),
			$this->row($this->t('time_limit_from', 'Time limit from'), 'usr_data.time_limit_from', $this->timestampProfileValue($profile, 'time_limit_from')),
			$this->row($this->t('time_limit_until', 'Time limit until'), 'usr_data.time_limit_until', $this->timestampProfileValue($profile, 'time_limit_until')),
			$this->row($this->t('time_limit_owner', 'Time limit owner'), 'usr_data.time_limit_owner', $this->profileValue($profile, 'time_limit_owner')),
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
				'type' => in_array($roleId, $globalRoleIds, true) ? $this->t('role_type_global', 'global') : $this->t('role_type_local_linked', 'local / linked'),
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

		return ((int)$profile[$key]) > 0 ? $this->t('value_yes', 'yes') : $this->t('value_no', 'no');
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
		$specific = $this->view->getBricks('ilias_user_debug_display');

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
