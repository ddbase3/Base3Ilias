<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\LinkTarget\Api\ILinkTargetService;
use ilCtrl;
use ilIniFile;
use ilObject;
use ilObjUser;
use ilRbacReview;

final class IliasDashboardDisplay implements IDisplay {

	private array $translations = [];

	public function __construct(
		private readonly IMvcView $view,
		private readonly ilIniFile $ilIliasIniFile,
		private readonly ilObjUser $ilUser,
		private readonly ilRbacReview $rbacreview,
		private readonly ilCtrl $ilCtrl,
		private readonly ILinkTargetService $linkTargetService
	) {}

	public static function getName(): string {
		return 'iliasdashboarddisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		$this->loadTranslations();

		return $this->t('help', 'Compact ILIAS administration dashboard.');
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->loadTranslations();
		$checks = $this->getChecks();
		$summary = $this->getSummary($checks);

		$this->view->setTemplate('Display/IliasDashboardDisplay.php');

		$this->view->assign('generatedAt', date('c'));
		$this->view->assign('summary', $summary);
		$this->view->assign('cards', $this->getCards($checks, $summary));
		$this->view->assign('quickLinks', $this->getQuickLinks());
		$this->view->assign('timelineItems', $this->getTimelineItems());
		$this->view->assign('pathChecks', $this->getPathCheckTiles($checks));
		$this->view->assign('translations', $this->translations);

		return $this->view->loadTemplate();
	}

	private function getChecks(): array {
		$absolutePath = $this->read('server', 'absolute_path');
		$clientPath = $this->resolvePath($absolutePath, $this->read('clients', 'path'));
		$defaultClient = $this->read('clients', 'default');
		$clientDirectory = $this->joinPath($clientPath, $defaultClient);
		$dataDirectory = $this->read('clients', 'datadir');
		$clientDataDirectory = $this->joinPath($dataDirectory, $defaultClient);
		$logPath = $this->read('log', 'path');
		$logFile = $this->joinPath($logPath, $this->read('log', 'file'));
		$errorPath = $this->read('log', 'error_path');

		return [
			$this->checkDirectory($this->t('check_ilias_root', 'ILIAS root'), $absolutePath, true, false),
			$this->checkDirectory($this->t('check_client_directory', 'Client directory'), $clientDirectory, true, false),
			$this->checkDirectory($this->t('check_data_directory', 'Data directory'), $dataDirectory, true, true),
			$this->checkDirectory($this->t('check_client_data_directory', 'Client data directory'), $clientDataDirectory, true, true),
			$this->checkDirectory($this->t('check_log_directory', 'Log directory'), $logPath, true, true),
			$this->checkFile($this->t('check_ilias_log', 'ILIAS log'), $logFile, true, true),
			$this->checkDirectory($this->t('check_error_log_directory', 'Error log directory'), $errorPath, true, true),
			$this->checkDirectory($this->t('check_base3ilias', 'Base3Ilias'), $this->joinPath(\DIR_COMPONENTS, 'Base3/Base3Ilias'), true, false),
		];
	}

	private function getCards(array $checks, array $summary): array {
		$absolutePath = $this->read('server', 'absolute_path');
		$defaultClient = $this->read('clients', 'default');
		$dataDirectory = $this->read('clients', 'datadir');
		$logPath = $this->read('log', 'path');
		$logFile = $this->joinPath($logPath, $this->read('log', 'file'));
		$errorPath = $this->read('log', 'error_path');
		$userId = $this->getCurrentUserId();
		$globalRoles = $this->rbacreview->assignedGlobalRoles($userId);
		$assignedRoles = $this->rbacreview->assignedRoles($userId);

		return [
			[
				'type' => 'health',
				'title' => $this->t('card_system_status', 'System status'),
				'status' => (string)$summary['status'],
				'value' => (string)$summary['score'] . '%',
				'meta' => $this->t('summary_counts', '%1$d OK, %2$d warnings, %3$d errors', $summary['ok'], $summary['warning'], $summary['error']),
				'items' => [
					'checks' => (string)$summary['total'],
					'ok' => (string)$summary['ok'],
					'warning' => (string)$summary['warning'],
					'error' => (string)$summary['error'],
				],
			],
			[
				'type' => 'client',
				'title' => $this->t('card_instance', 'Instance'),
				'status' => $this->directoryStatus($absolutePath),
				'value' => $defaultClient !== '' ? $defaultClient : $this->t('no_client', 'No client'),
				'meta' => $this->read('server', 'timezone'),
				'items' => [
					'HTTP' => $this->read('server', 'http_path'),
					$this->t('item_root', 'Root') => $absolutePath,
					$this->t('item_data', 'Data') => $dataDirectory,
				],
			],
			[
				'type' => 'log',
				'title' => $this->t('card_ilias_log', 'ILIAS log'),
				'status' => $this->fileStatus($logFile),
				'value' => $this->pathShortValue($logFile),
				'meta' => $this->fileMeta($logFile),
				'items' => [
					$this->t('item_level', 'Level') => $this->read('log', 'level'),
					$this->t('item_path', 'Path') => $logPath,
				],
			],
			[
				'type' => 'errors',
				'title' => $this->t('card_error_logs', 'Error logs'),
				'status' => $this->directoryStatus($errorPath),
				'value' => $this->t('files_count', '%d files', $this->errorLogCount($errorPath)),
				'meta' => $this->latestErrorLogMeta($errorPath),
				'items' => [
					$this->t('item_path', 'Path') => $errorPath,
					$this->t('item_latest', 'Latest') => $this->latestErrorLogFile($errorPath),
				],
			],
			[
				'type' => 'user',
				'title' => $this->t('card_current_user', 'Current user'),
				'status' => ilObjUser::_lookupActive($userId) ? 'ok' : 'warning',
				'value' => ilObjUser::_lookupLogin($userId),
				'meta' => $this->t('user_id_value', 'User ID %d', $userId),
				'items' => [
					$this->t('item_name', 'Name') => $this->currentUserName($userId),
					$this->t('item_language', 'Language') => ilObjUser::_lookupLanguage($userId),
					$this->t('item_global_roles', 'Global roles') => (string)count($globalRoles),
					$this->t('item_all_roles', 'All roles') => (string)count($assignedRoles),
				],
			],
			[
				'type' => 'request',
				'title' => $this->t('card_request', 'Request'),
				'status' => 'info',
				'value' => $this->safeValue($this->ilCtrl->getCmd(), $this->t('no_command', 'No command')),
				'meta' => $this->safeValue($this->ilCtrl->getCmdClass(), $this->t('no_command_class', 'No command class')),
				'items' => [
					$this->t('item_next_class', 'Next class') => $this->formatValue($this->ilCtrl->getNextClass()),
					$this->t('item_method', 'Method') => $this->serverValue('REQUEST_METHOD'),
					$this->t('item_async', 'Async') => $this->ilCtrl->isAsynch() ? $this->t('value_yes', 'yes') : $this->t('value_no', 'no'),
				],
			],
		];
	}

	private function getQuickLinks(): array {
		return [
			$this->quickLink($this->t('quick_config_title', 'Config'), $this->t('quick_config_description', 'INI values and derived paths.'), IliasConfigAdminDisplay::getName(), 'config'),
			$this->quickLink($this->t('quick_health_title', 'Health'), $this->t('quick_health_description', 'File system, paths and tools.'), IliasSystemHealthDisplay::getName(), 'health'),
			$this->quickLink($this->t('quick_log_title', 'ILIAS log'), $this->t('quick_log_description', 'Live log with automatic refresh.'), IliasLogAdminDisplay::getName(), 'log'),
			$this->quickLink($this->t('quick_errors_title', 'Error logs'), $this->t('quick_errors_description', 'Expand and read error files.'), IliasErrorLogAdminDisplay::getName(), 'errors'),
			$this->quickLink($this->t('quick_request_title', 'Request'), $this->t('quick_request_description', 'Controller and request context.'), IliasRequestDebugDisplay::getName(), 'request'),
			$this->quickLink($this->t('quick_permissions_title', 'Permissions'), $this->t('quick_permissions_description', 'RBAC roles and operations.'), IliasPermissionDebugDisplay::getName(), 'permission'),
			$this->quickLink($this->t('quick_object_title', 'Object'), $this->t('quick_object_description', 'Object, path and children.'), IliasObjectDebugDisplay::getName(), 'object'),
		];
	}

	private function quickLink(string $title, string $description, string $command, string $type): array {
		return [
			'title' => $title,
			'description' => $description,
			'command' => $command,
			'type' => $type,
			'url' => $this->linkTargetService->getLink([
				'name' => $command,
				'out' => 'html',
			]),
		];
	}

	private function getTimelineItems(): array {
		$logPath = $this->read('log', 'path');
		$logFile = $this->joinPath($logPath, $this->read('log', 'file'));
		$errorPath = $this->read('log', 'error_path');

		return [
			[
				'label' => $this->t('timeline_dashboard', 'Dashboard'),
				'value' => date('Y-m-d H:i:s'),
				'status' => 'info',
			],
			[
				'label' => $this->t('timeline_ilias_log', 'ILIAS log'),
				'value' => $this->mtimeValue($logFile),
				'status' => $this->fileStatus($logFile),
			],
			[
				'label' => $this->t('timeline_latest_error_file', 'Latest error file'),
				'value' => $this->latestErrorLogMeta($errorPath),
				'status' => $this->directoryStatus($errorPath),
			],
		];
	}

	private function getPathCheckTiles(array $checks): array {
		$out = [];

		foreach ($checks as $check) {
			$out[] = [
				'label' => (string)$check['label'],
				'status' => (string)$check['status'],
				'message' => (string)$check['message'],
				'path' => (string)$check['path'],
			];
		}

		return $out;
	}

	private function checkDirectory(string $label, string $path, bool $mustBeReadable, bool $mustBeWritable): array {
		if ($path === '') {
			return [
				'label' => $label,
				'path' => '',
				'status' => 'error',
				'message' => $this->t('check_not_configured', 'Not configured'),
			];
		}

		if (!is_dir($path)) {
			return [
				'label' => $label,
				'path' => $path,
				'status' => 'error',
				'message' => $this->t('check_directory_missing', 'Directory is missing'),
			];
		}

		if ($mustBeReadable && !is_readable($path)) {
			return [
				'label' => $label,
				'path' => $path,
				'status' => 'error',
				'message' => $this->t('check_not_readable', 'Not readable'),
			];
		}

		if ($mustBeWritable && !is_writable($path)) {
			return [
				'label' => $label,
				'path' => $path,
				'status' => 'warning',
				'message' => $this->t('check_not_writable', 'Not writable'),
			];
		}

		return [
			'label' => $label,
			'path' => $path,
			'status' => 'ok',
			'message' => $this->t('status_ok', 'OK'),
		];
	}

	private function checkFile(string $label, string $path, bool $mustBeReadable, bool $mustBeWritable): array {
		if ($path === '') {
			return [
				'label' => $label,
				'path' => '',
				'status' => 'error',
				'message' => $this->t('check_not_configured', 'Not configured'),
			];
		}

		if (!is_file($path)) {
			return [
				'label' => $label,
				'path' => $path,
				'status' => 'error',
				'message' => $this->t('check_file_missing', 'File is missing'),
			];
		}

		if ($mustBeReadable && !is_readable($path)) {
			return [
				'label' => $label,
				'path' => $path,
				'status' => 'error',
				'message' => $this->t('check_not_readable', 'Not readable'),
			];
		}

		if ($mustBeWritable && !is_writable($path)) {
			return [
				'label' => $label,
				'path' => $path,
				'status' => 'warning',
				'message' => $this->t('check_not_writable', 'Not writable'),
			];
		}

		return [
			'label' => $label,
			'path' => $path,
			'status' => 'ok',
			'message' => $this->t('status_ok', 'OK'),
		];
	}

	private function getSummary(array $checks): array {
		$ok = $this->countByStatus($checks, 'ok');
		$warning = $this->countByStatus($checks, 'warning');
		$error = $this->countByStatus($checks, 'error');
		$total = count($checks);
		$status = $this->getWorstStatus($checks);
		$score = $total > 0 ? (int)round(($ok / $total) * 100) : 0;

		return [
			'status' => $status,
			'ok' => $ok,
			'warning' => $warning,
			'error' => $error,
			'total' => $total,
			'score' => $score,
			'message' => $this->summaryMessage($status),
		];
	}

	private function summaryMessage(string $status): string {
		return match ($status) {
			'ok' => $this->t('summary_ok', 'The most important basic checks are clear.'),
			'warning' => $this->t('summary_warning', 'At least one basic check has warnings.'),
			default => $this->t('summary_error', 'At least one important basic check failed.'),
		};
	}

	private function getWorstStatus(array $checks): string {
		foreach ($checks as $check) {
			if ((string)$check['status'] === 'error') {
				return 'error';
			}
		}

		foreach ($checks as $check) {
			if ((string)$check['status'] === 'warning') {
				return 'warning';
			}
		}

		return 'ok';
	}

	private function countByStatus(array $checks, string $status): int {
		$count = 0;

		foreach ($checks as $check) {
			if ((string)$check['status'] === $status) {
				$count++;
			}
		}

		return $count;
	}

	private function fileStatus(string $path): string {
		if ($path === '' || !is_file($path) || !is_readable($path)) {
			return 'error';
		}

		if (!is_writable($path)) {
			return 'warning';
		}

		return 'ok';
	}

	private function directoryStatus(string $path): string {
		if ($path === '' || !is_dir($path) || !is_readable($path)) {
			return 'error';
		}

		if (!is_writable($path)) {
			return 'warning';
		}

		return 'ok';
	}

	private function fileMeta(string $path): string {
		if ($path === '' || !is_file($path)) {
			return $this->t('not_found', 'Not found');
		}

		$size = filesize($path) ?: 0;
		$mtime = filemtime($path);

		return $this->formatBytes((int)$size) . ($mtime ? $this->t('modified_prefix', ', modified ') . date('Y-m-d H:i:s', (int)$mtime) : '');
	}

	private function errorLogCount(string $path): int {
		if ($path === '' || !is_dir($path) || !is_readable($path)) {
			return 0;
		}

		$items = scandir($path);

		if ($items === false) {
			return 0;
		}

		$count = 0;

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			if (is_file($this->joinPath($path, $item))) {
				$count++;
			}
		}

		return $count;
	}

	private function latestErrorLogMeta(string $path): string {
		$file = $this->latestErrorLogFile($path);

		if ($file === '') {
			return $this->t('no_files', 'No files');
		}

		$fullPath = $this->joinPath($path, $file);
		$mtime = filemtime($fullPath);

		return $file . ($mtime ? $this->t('modified_prefix', ', modified ') . date('Y-m-d H:i:s', (int)$mtime) : '');
	}

	private function latestErrorLogFile(string $path): string {
		if ($path === '' || !is_dir($path) || !is_readable($path)) {
			return '';
		}

		$items = scandir($path);

		if ($items === false) {
			return '';
		}

		$latestFile = '';
		$latestMtime = 0;

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$fullPath = $this->joinPath($path, $item);

			if (!is_file($fullPath)) {
				continue;
			}

			$mtime = filemtime($fullPath) ?: 0;

			if ($mtime > $latestMtime) {
				$latestFile = $item;
				$latestMtime = $mtime;
			}
		}

		return $latestFile;
	}

	private function mtimeValue(string $path): string {
		if ($path === '' || !file_exists($path)) {
			return $this->t('not_found', 'Not found');
		}

		$mtime = filemtime($path);

		if (!$mtime) {
			return $this->t('unknown', 'Unknown');
		}

		return date('Y-m-d H:i:s', (int)$mtime);
	}

	private function currentUserName(int $userId): string {
		$name = ilObjUser::_lookupName($userId);
		$firstName = trim((string)($name['firstname'] ?? ''));
		$lastName = trim((string)($name['lastname'] ?? ''));

		return trim($firstName . ' ' . $lastName);
	}

	private function safeValue(string $value, string $fallback): string {
		$value = trim($value);

		if ($value === '') {
			return $fallback;
		}

		return $value;
	}

	private function pathShortValue(string $path): string {
		if ($path === '') {
			return $this->t('not_configured', 'Not configured');
		}

		return basename($path);
	}

	private function read(string $section, string $key): string {
		return trim((string)$this->ilIliasIniFile->readVariable($section, $key));
	}

	private function resolvePath(string $basePath, string $path): string {
		if ($path === '') {
			return '';
		}

		if ($this->isAbsolutePath($path)) {
			return $path;
		}

		if ($basePath === '') {
			return $path;
		}

		return $this->joinPath($basePath, $path);
	}

	private function joinPath(string $basePath, string $path): string {
		$basePath = trim($basePath);
		$path = trim($path);

		if ($basePath === '') {
			return $path;
		}

		if ($path === '') {
			return $basePath;
		}

		return rtrim($basePath, '/\\') . '/' . ltrim($path, '/\\');
	}

	private function isAbsolutePath(string $path): bool {
		if (str_starts_with($path, '/')) {
			return true;
		}

		return preg_match('/^[a-zA-Z]:[\/\\\\]/', $path) === 1;
	}

	private function getCurrentUserId(): int {
		return (int)$this->ilUser->getId();
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

	private function formatBytes(int $bytes): string {
		if ($bytes >= 1073741824) {
			return round($bytes / 1073741824, 2) . ' GB';
		}

		if ($bytes >= 1048576) {
			return round($bytes / 1048576, 2) . ' MB';
		}

		if ($bytes >= 1024) {
			return round($bytes / 1024, 2) . ' KB';
		}

		return $bytes . ' B';
	}

	private function loadTranslations(): void {
		$this->view->setPath(\DIR_COMPONENTS . 'Base3/Base3Ilias');
		$this->view->loadBricks('Display');

		$common = $this->view->getBricks('base3ilias_common');
		$specific = $this->view->getBricks('ilias_dashboard_display');

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
