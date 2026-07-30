<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use ilIniFile;

final class IliasSystemHealthDisplay implements IDisplay {

	private array $translations = [];

	public function __construct(
		private readonly IMvcView $view,
		private readonly ilIniFile $ilIliasIniFile
	) {}

	public static function getName(): string {
		return 'iliassystemhealthdisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		$this->loadTranslations();

		return $this->t('help', 'ILIAS system health overview.');
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->loadTranslations();
		$sections = $this->getSections();

		$this->view->setTemplate('Display/IliasSystemHealthDisplay.php');

		$this->view->assign('sections', $sections);
		$this->view->assign('summary', $this->getSummary($sections));
		$this->view->assign('generatedAt', date('c'));
		$this->view->assign('translations', $this->translations);

		return $this->view->loadTemplate();
	}

	private function getSections(): array {
		$absolutePath = $this->read('server', 'absolute_path');
		$clientPath = $this->resolvePath($absolutePath, $this->read('clients', 'path'));
		$defaultClient = $this->read('clients', 'default');
		$clientDataDir = $this->read('clients', 'datadir');

		$defaultClientPath = $this->joinPath($clientPath, $defaultClient);
		$clientIniFile = $this->joinPath($defaultClientPath, $this->read('clients', 'inifile'));
		$defaultClientDataDir = $this->joinPath($clientDataDir, $defaultClient);

		$logPath = $this->read('log', 'path');
		$logFile = $this->read('log', 'file');
		$logFullPath = $this->joinPath($logPath, $logFile);
		$errorPath = $this->read('log', 'error_path');

		$componentPath = rtrim(\DIR_COMPONENTS, '/\\');
		$base3IliasPath = $this->joinPath(\DIR_COMPONENTS, 'Base3/Base3Ilias');
		$base3IliasTemplatePath = $this->joinPath($base3IliasPath, 'tpl/Display');

		return [
			[
				'title' => $this->t('section_server_client_paths_title', 'Server and client paths'),
				'description' => $this->t('section_server_client_paths_description', 'Central ILIAS installation and client paths.'),
				'rows' => [
					$this->checkDirectory($this->t('ilias_absolute_path', 'ILIAS absolute path'), '[server] absolute_path', $absolutePath, true, true, false),
					$this->checkDirectory($this->t('public_client_path', 'Public client path'), '[clients] path', $clientPath, true, true, false),
					$this->checkDirectory($this->t('default_client_directory', 'Default client directory'), '[clients] path + default', $defaultClientPath, true, true, false),
					$this->checkFile($this->t('client_ini_file', 'Client INI file'), '[clients] path + default + inifile', $clientIniFile, true, true, false),
					$this->checkDirectory($this->t('data_directory', 'Data directory'), '[clients] datadir', $clientDataDir, true, true, true),
					$this->checkDirectory($this->t('default_client_data_directory', 'Default client data directory'), '[clients] datadir + default', $defaultClientDataDir, true, true, true),
				],
			],
			[
				'title' => $this->t('section_logs_title', 'Logs'),
				'description' => $this->t('section_logs_description', 'Log directories and log files from the ILIAS configuration.'),
				'rows' => [
					$this->checkDirectory($this->t('log_directory', 'Log directory'), '[log] path', $logPath, true, true, true),
					$this->checkFile($this->t('log_file', 'Log file'), '[log] path + file', $logFullPath, true, true, true),
					$this->checkDirectory($this->t('error_log_directory', 'Error log directory'), '[log] error_path', $errorPath, true, true, true),
				],
			],
			[
				'title' => $this->t('section_component_paths_title', 'BASE3 / component paths'),
				'description' => $this->t('section_component_paths_description', 'Paths of the BASE3 ILIAS integration.'),
				'rows' => [
					$this->checkDirectory($this->t('components_directory', 'Components directory'), 'DIR_COMPONENTS', $componentPath, true, true, false),
					$this->checkDirectory($this->t('base3ilias_component_directory', 'Base3Ilias component directory'), 'DIR_COMPONENTS + Base3/Base3Ilias', $base3IliasPath, true, true, false),
					$this->checkDirectory($this->t('base3ilias_template_directory', 'Base3Ilias template directory'), 'Base3Ilias/tpl/Display', $base3IliasTemplatePath, true, true, false),
				],
			],
			[
				'title' => $this->t('section_external_tools_title', 'External tools'),
				'description' => $this->t('section_external_tools_description', 'Configured external programs from [tools]. Empty optional values are shown as information.'),
				'rows' => [
					$this->checkExecutable($this->t('imagemagick_convert', 'ImageMagick Convert'), '[tools] convert', $this->read('tools', 'convert'), false),
					$this->checkExecutable($this->t('zip', 'Zip'), '[tools] zip', $this->read('tools', 'zip'), false),
					$this->checkExecutable($this->t('unzip', 'Unzip'), '[tools] unzip', $this->read('tools', 'unzip'), false),
					$this->checkExecutable($this->t('java', 'Java'), '[tools] java', $this->read('tools', 'java'), false),
					$this->checkExecutable($this->t('htmldoc', 'HTMLDoc'), '[tools] htmldoc', $this->read('tools', 'htmldoc'), false),
					$this->checkExecutable($this->t('ffmpeg', 'FFmpeg'), '[tools] ffmpeg', $this->read('tools', 'ffmpeg'), false),
					$this->checkExecutable($this->t('ghostscript', 'Ghostscript'), '[tools] ghostscript', $this->read('tools', 'ghostscript'), false),
					$this->checkExecutable($this->t('latex', 'LaTeX'), '[tools] latex', $this->read('tools', 'latex'), false),
					$this->checkExecutable($this->t('virus_scan_command', 'Virus scan command'), '[tools] scancommand', $this->read('tools', 'scancommand'), false),
					$this->checkExecutable($this->t('clean_command', 'Clean command'), '[tools] cleancommand', $this->read('tools', 'cleancommand'), false),
					$this->checkExecutable($this->t('fop', 'FOP'), '[tools] fop', $this->read('tools', 'fop'), false),
					$this->checkExecutable($this->t('less_compiler', 'Less compiler'), '[tools] lessc', $this->read('tools', 'lessc'), false),
					$this->checkExecutable($this->t('phantomjs', 'PhantomJS'), '[tools] phantomjs', $this->read('tools', 'phantomjs'), false),
				],
			],
		];
	}

	private function checkDirectory(string $label, string $source, string $path, bool $required, bool $mustBeReadable, bool $mustBeWritable): array {
		if ($path === '') {
			return $this->row($label, $source, $path, 'directory', $required ? 'error' : 'info', $required ? $this->t('path_not_configured', 'Path is not configured.') : $this->t('optional_path_not_configured', 'Optional path is not configured.'));
		}

		if (!file_exists($path)) {
			return $this->row($label, $source, $path, 'directory', 'error', $this->t('directory_missing', 'Directory does not exist.'));
		}

		if (!is_dir($path)) {
			return $this->row($label, $source, $path, 'directory', 'error', $this->t('not_a_directory', 'Path exists, but is not a directory.'));
		}

		if ($mustBeReadable && !is_readable($path)) {
			return $this->row($label, $source, $path, 'directory', 'error', $this->t('directory_not_readable', 'Directory is not readable.'), $this->getPathMeta($path));
		}

		if ($mustBeWritable && !is_writable($path)) {
			return $this->row($label, $source, $path, 'directory', 'warning', $this->t('directory_not_writable', 'Directory is not writable.'), $this->getPathMeta($path));
		}

		return $this->row($label, $source, $path, 'directory', 'ok', $this->t('directory_available', 'Directory is available.'), $this->getPathMeta($path));
	}

	private function checkFile(string $label, string $source, string $path, bool $required, bool $mustBeReadable, bool $mustBeWritable): array {
		if ($path === '') {
			return $this->row($label, $source, $path, 'file', $required ? 'error' : 'info', $required ? $this->t('path_not_configured', 'Path is not configured.') : $this->t('optional_file_not_configured', 'Optional file is not configured.'));
		}

		if (!file_exists($path)) {
			return $this->row($label, $source, $path, 'file', 'error', $this->t('file_missing', 'File does not exist.'));
		}

		if (!is_file($path)) {
			return $this->row($label, $source, $path, 'file', 'error', $this->t('not_a_file', 'Path exists, but is not a file.'));
		}

		if ($mustBeReadable && !is_readable($path)) {
			return $this->row($label, $source, $path, 'file', 'error', $this->t('file_not_readable', 'File is not readable.'), $this->getPathMeta($path));
		}

		if ($mustBeWritable && !is_writable($path)) {
			return $this->row($label, $source, $path, 'file', 'warning', $this->t('file_not_writable', 'File is not writable.'), $this->getPathMeta($path));
		}

		return $this->row($label, $source, $path, 'file', 'ok', $this->t('file_available', 'File is available.'), $this->getPathMeta($path));
	}

	private function checkExecutable(string $label, string $source, string $path, bool $required): array {
		if ($path === '') {
			return $this->row($label, $source, $path, 'executable', $required ? 'error' : 'info', $required ? $this->t('executable_not_configured', 'Executable is not configured.') : $this->t('optional_executable_not_configured', 'Optional executable is not configured.'));
		}

		if (!file_exists($path)) {
			return $this->row($label, $source, $path, 'executable', 'warning', $this->t('executable_missing', 'Executable does not exist at the configured path.'));
		}

		if (!is_file($path)) {
			return $this->row($label, $source, $path, 'executable', 'warning', $this->t('configured_path_not_file', 'Configured path is not a file.'));
		}

		if (!is_executable($path)) {
			return $this->row($label, $source, $path, 'executable', 'warning', $this->t('file_not_executable', 'File is not executable.'), $this->getPathMeta($path));
		}

		return $this->row($label, $source, $path, 'executable', 'ok', $this->t('executable_available', 'Executable is available.'), $this->getPathMeta($path));
	}

	private function row(string $label, string $source, string $path, string $type, string $status, string $message, array $meta = []): array {
		return [
			'label' => $label,
			'source' => $source,
			'path' => $path,
			'type' => $type,
			'status' => $status,
			'message' => $message,
			'meta' => $meta,
		];
	}

	private function getPathMeta(string $path): array {
		$meta = [];

		if (!file_exists($path)) {
			return $meta;
		}

		$perms = fileperms($path);
		if ($perms !== false) {
			$meta[] = [
				'label' => $this->t('permissions', 'Permissions'),
				'value' => substr(sprintf('%o', $perms), -4),
			];
		}

		$owner = fileowner($path);
		if ($owner !== false) {
			$meta[] = [
				'label' => $this->t('owner_uid', 'Owner UID'),
				'value' => (string)$owner,
			];
		}

		$group = filegroup($path);
		if ($group !== false) {
			$meta[] = [
				'label' => $this->t('group_gid', 'Group GID'),
				'value' => (string)$group,
			];
		}

		$mtime = filemtime($path);
		if ($mtime !== false) {
			$meta[] = [
				'label' => $this->t('modified', 'Modified'),
				'value' => date('Y-m-d H:i:s', $mtime),
			];
		}

		if (is_file($path)) {
			$size = filesize($path);
			if ($size !== false) {
				$meta[] = [
					'label' => $this->t('size', 'Size'),
					'value' => $this->formatBytes($size),
				];
			}
		}

		return $meta;
	}

	private function getSummary(array $sections): array {
		$summary = [
			'ok' => 0,
			'warning' => 0,
			'error' => 0,
			'info' => 0,
			'total' => 0,
			'status' => 'ok',
		];

		foreach ($sections as $section) {
			foreach ((array)$section['rows'] as $row) {
				$status = (string)$row['status'];

				if (isset($summary[$status])) {
					$summary[$status]++;
				}

				$summary['total']++;
			}
		}

		if ($summary['error'] > 0) {
			$summary['status'] = 'error';
		} elseif ($summary['warning'] > 0) {
			$summary['status'] = 'warning';
		}

		return $summary;
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
		$specific = $this->view->getBricks('ilias_system_health_display');

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
