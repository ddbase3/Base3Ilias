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
		return 'Compact ILIAS administration dashboard.';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$checks = $this->getChecks();
		$summary = $this->getSummary($checks);

		$this->view->setPath(\DIR_COMPONENTS . 'Base3/Base3Ilias');
		$this->view->setTemplate('Display/IliasDashboardDisplay.php');

		$this->view->assign('generatedAt', date('c'));
		$this->view->assign('summary', $summary);
		$this->view->assign('cards', $this->getCards($checks, $summary));
		$this->view->assign('quickLinks', $this->getQuickLinks());
		$this->view->assign('timelineItems', $this->getTimelineItems());
		$this->view->assign('pathChecks', $this->getPathCheckTiles($checks));

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
			$this->checkDirectory('ILIAS Root', $absolutePath, true, false),
			$this->checkDirectory('Client Directory', $clientDirectory, true, false),
			$this->checkDirectory('Data Directory', $dataDirectory, true, true),
			$this->checkDirectory('Client Data Directory', $clientDataDirectory, true, true),
			$this->checkDirectory('Log Directory', $logPath, true, true),
			$this->checkFile('ILIAS Log', $logFile, true, true),
			$this->checkDirectory('Error Log Directory', $errorPath, true, true),
			$this->checkDirectory('Base3Ilias', $this->joinPath(\DIR_COMPONENTS, 'Base3/Base3Ilias'), true, false),
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
				'title' => 'Systemstatus',
				'status' => (string)$summary['status'],
				'value' => (string)$summary['score'] . '%',
				'meta' => $summary['ok'] . ' OK, ' . $summary['warning'] . ' Warnungen, ' . $summary['error'] . ' Fehler',
				'items' => [
					'checks' => (string)$summary['total'],
					'ok' => (string)$summary['ok'],
					'warning' => (string)$summary['warning'],
					'error' => (string)$summary['error'],
				],
			],
			[
				'type' => 'client',
				'title' => 'Instanz',
				'status' => $this->directoryStatus($absolutePath),
				'value' => $defaultClient !== '' ? $defaultClient : 'Kein Client',
				'meta' => $this->read('server', 'timezone'),
				'items' => [
					'HTTP' => $this->read('server', 'http_path'),
					'Root' => $absolutePath,
					'Data' => $dataDirectory,
				],
			],
			[
				'type' => 'log',
				'title' => 'ILIAS Log',
				'status' => $this->fileStatus($logFile),
				'value' => $this->pathShortValue($logFile),
				'meta' => $this->fileMeta($logFile),
				'items' => [
					'Level' => $this->read('log', 'level'),
					'Pfad' => $logPath,
				],
			],
			[
				'type' => 'errors',
				'title' => 'Error Logs',
				'status' => $this->directoryStatus($errorPath),
				'value' => $this->errorLogCount($errorPath) . ' Dateien',
				'meta' => $this->latestErrorLogMeta($errorPath),
				'items' => [
					'Pfad' => $errorPath,
					'Neueste' => $this->latestErrorLogFile($errorPath),
				],
			],
			[
				'type' => 'user',
				'title' => 'Aktueller User',
				'status' => ilObjUser::_lookupActive($userId) ? 'ok' : 'warning',
				'value' => ilObjUser::_lookupLogin($userId),
				'meta' => 'User ID ' . $userId,
				'items' => [
					'Name' => $this->currentUserName($userId),
					'Sprache' => ilObjUser::_lookupLanguage($userId),
					'Globale Rollen' => (string)count($globalRoles),
					'Rollen gesamt' => (string)count($assignedRoles),
				],
			],
			[
				'type' => 'request',
				'title' => 'Request',
				'status' => 'info',
				'value' => $this->safeValue($this->ilCtrl->getCmd(), 'Kein Command'),
				'meta' => $this->safeValue($this->ilCtrl->getCmdClass(), 'Keine Command Class'),
				'items' => [
					'Next Class' => $this->formatValue($this->ilCtrl->getNextClass()),
					'Methode' => $this->serverValue('REQUEST_METHOD'),
					'Async' => $this->ilCtrl->isAsynch() ? 'yes' : 'no',
				],
			],
		];
	}

	private function getQuickLinks(): array {
		return [
			$this->quickLink('Config', 'Ini-Werte und abgeleitete Pfade.', IliasConfigAdminDisplay::getName(), 'config'),
			$this->quickLink('Health', 'Dateisystem, Pfade und Tools.', IliasSystemHealthDisplay::getName(), 'health'),
			$this->quickLink('ILIAS Log', 'Live-Log mit Auto-Refresh.', IliasLogAdminDisplay::getName(), 'log'),
			$this->quickLink('Error Logs', 'Error-Dateien aufklappen und lesen.', IliasErrorLogAdminDisplay::getName(), 'errors'),
			$this->quickLink('Request', 'Controller- und Request-Kontext.', IliasRequestDebugDisplay::getName(), 'request'),
			$this->quickLink('Permissions', 'RBAC-Rollen und Operationen.', IliasPermissionDebugDisplay::getName(), 'permission'),
			$this->quickLink('Object', 'Objekt, Pfad und Kinder.', IliasObjectDebugDisplay::getName(), 'object'),
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
				'label' => 'Dashboard',
				'value' => date('Y-m-d H:i:s'),
				'status' => 'info',
			],
			[
				'label' => 'ILIAS Log',
				'value' => $this->mtimeValue($logFile),
				'status' => $this->fileStatus($logFile),
			],
			[
				'label' => 'Neueste Error-Datei',
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
				'message' => 'Nicht konfiguriert',
			];
		}

		if (!is_dir($path)) {
			return [
				'label' => $label,
				'path' => $path,
				'status' => 'error',
				'message' => 'Verzeichnis fehlt',
			];
		}

		if ($mustBeReadable && !is_readable($path)) {
			return [
				'label' => $label,
				'path' => $path,
				'status' => 'error',
				'message' => 'Nicht lesbar',
			];
		}

		if ($mustBeWritable && !is_writable($path)) {
			return [
				'label' => $label,
				'path' => $path,
				'status' => 'warning',
				'message' => 'Nicht schreibbar',
			];
		}

		return [
			'label' => $label,
			'path' => $path,
			'status' => 'ok',
			'message' => 'OK',
		];
	}

	private function checkFile(string $label, string $path, bool $mustBeReadable, bool $mustBeWritable): array {
		if ($path === '') {
			return [
				'label' => $label,
				'path' => '',
				'status' => 'error',
				'message' => 'Nicht konfiguriert',
			];
		}

		if (!is_file($path)) {
			return [
				'label' => $label,
				'path' => $path,
				'status' => 'error',
				'message' => 'Datei fehlt',
			];
		}

		if ($mustBeReadable && !is_readable($path)) {
			return [
				'label' => $label,
				'path' => $path,
				'status' => 'error',
				'message' => 'Nicht lesbar',
			];
		}

		if ($mustBeWritable && !is_writable($path)) {
			return [
				'label' => $label,
				'path' => $path,
				'status' => 'warning',
				'message' => 'Nicht schreibbar',
			];
		}

		return [
			'label' => $label,
			'path' => $path,
			'status' => 'ok',
			'message' => 'OK',
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
			'ok' => 'Die wichtigsten Basisprüfungen sind unauffällig.',
			'warning' => 'Es gibt Warnungen bei mindestens einer Basisprüfung.',
			default => 'Mindestens eine wichtige Basisprüfung ist fehlgeschlagen.',
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
			return 'Nicht gefunden';
		}

		$size = filesize($path) ?: 0;
		$mtime = filemtime($path);

		return $this->formatBytes((int)$size) . ($mtime ? ', geändert ' . date('Y-m-d H:i:s', (int)$mtime) : '');
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
			return 'Keine Dateien';
		}

		$fullPath = $this->joinPath($path, $file);
		$mtime = filemtime($fullPath);

		return $file . ($mtime ? ', geändert ' . date('Y-m-d H:i:s', (int)$mtime) : '');
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
			return 'Nicht gefunden';
		}

		$mtime = filemtime($path);

		if (!$mtime) {
			return 'Unbekannt';
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
			return 'Nicht konfiguriert';
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
}
