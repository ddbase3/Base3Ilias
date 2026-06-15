<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use ilIniFile;

final class IliasSystemHealthDisplay implements IDisplay {

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
		return 'ILIAS system health overview.';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$sections = $this->getSections();

		$this->view->setPath(\DIR_COMPONENTS . 'Base3/Base3Ilias');
		$this->view->setTemplate('Display/IliasSystemHealthDisplay.php');

		$this->view->assign('sections', $sections);
		$this->view->assign('summary', $this->getSummary($sections));
		$this->view->assign('generatedAt', date('c'));

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
				'title' => 'Server & Client Paths',
				'description' => 'Zentrale ILIAS-Installations- und Client-Pfade.',
				'rows' => [
					$this->checkDirectory('ILIAS Absolute Path', '[server] absolute_path', $absolutePath, true, true, false),
					$this->checkDirectory('Public Client Path', '[clients] path', $clientPath, true, true, false),
					$this->checkDirectory('Default Client Directory', '[clients] path + default', $defaultClientPath, true, true, false),
					$this->checkFile('Client Ini File', '[clients] path + default + inifile', $clientIniFile, true, true, false),
					$this->checkDirectory('Data Directory', '[clients] datadir', $clientDataDir, true, true, true),
					$this->checkDirectory('Default Client Data Directory', '[clients] datadir + default', $defaultClientDataDir, true, true, true),
				],
			],
			[
				'title' => 'Logs',
				'description' => 'Log-Verzeichnisse und Logdateien aus der ILIAS-Konfiguration.',
				'rows' => [
					$this->checkDirectory('Log Directory', '[log] path', $logPath, true, true, true),
					$this->checkFile('Log File', '[log] path + file', $logFullPath, true, true, true),
					$this->checkDirectory('Error Log Directory', '[log] error_path', $errorPath, true, true, true),
				],
			],
			[
				'title' => 'BASE3 / Component Paths',
				'description' => 'Pfade der BASE3-Ilias-Integration.',
				'rows' => [
					$this->checkDirectory('Components Directory', 'DIR_COMPONENTS', $componentPath, true, true, false),
					$this->checkDirectory('Base3Ilias Component Directory', 'DIR_COMPONENTS + Base3/Base3Ilias', $base3IliasPath, true, true, false),
					$this->checkDirectory('Base3Ilias Template Directory', 'Base3Ilias/tpl/Display', $base3IliasTemplatePath, true, true, false),
				],
			],
			[
				'title' => 'External Tools',
				'description' => 'Konfigurierte externe Programme aus [tools]. Leere optionale Werte werden als Info angezeigt.',
				'rows' => [
					$this->checkExecutable('ImageMagick Convert', '[tools] convert', $this->read('tools', 'convert'), false),
					$this->checkExecutable('Zip', '[tools] zip', $this->read('tools', 'zip'), false),
					$this->checkExecutable('Unzip', '[tools] unzip', $this->read('tools', 'unzip'), false),
					$this->checkExecutable('Java', '[tools] java', $this->read('tools', 'java'), false),
					$this->checkExecutable('HTMLDoc', '[tools] htmldoc', $this->read('tools', 'htmldoc'), false),
					$this->checkExecutable('FFmpeg', '[tools] ffmpeg', $this->read('tools', 'ffmpeg'), false),
					$this->checkExecutable('Ghostscript', '[tools] ghostscript', $this->read('tools', 'ghostscript'), false),
					$this->checkExecutable('LaTeX', '[tools] latex', $this->read('tools', 'latex'), false),
					$this->checkExecutable('Virus Scan Command', '[tools] scancommand', $this->read('tools', 'scancommand'), false),
					$this->checkExecutable('Clean Command', '[tools] cleancommand', $this->read('tools', 'cleancommand'), false),
					$this->checkExecutable('FOP', '[tools] fop', $this->read('tools', 'fop'), false),
					$this->checkExecutable('Less Compiler', '[tools] lessc', $this->read('tools', 'lessc'), false),
					$this->checkExecutable('PhantomJS', '[tools] phantomjs', $this->read('tools', 'phantomjs'), false),
				],
			],
		];
	}

	private function checkDirectory(string $label, string $source, string $path, bool $required, bool $mustBeReadable, bool $mustBeWritable): array {
		if ($path === '') {
			return $this->row($label, $source, $path, 'directory', $required ? 'error' : 'info', $required ? 'Path is not configured.' : 'Optional path is not configured.');
		}

		if (!file_exists($path)) {
			return $this->row($label, $source, $path, 'directory', 'error', 'Directory does not exist.');
		}

		if (!is_dir($path)) {
			return $this->row($label, $source, $path, 'directory', 'error', 'Path exists, but is not a directory.');
		}

		if ($mustBeReadable && !is_readable($path)) {
			return $this->row($label, $source, $path, 'directory', 'error', 'Directory is not readable.', $this->getPathMeta($path));
		}

		if ($mustBeWritable && !is_writable($path)) {
			return $this->row($label, $source, $path, 'directory', 'warning', 'Directory is not writable.', $this->getPathMeta($path));
		}

		return $this->row($label, $source, $path, 'directory', 'ok', 'Directory is available.', $this->getPathMeta($path));
	}

	private function checkFile(string $label, string $source, string $path, bool $required, bool $mustBeReadable, bool $mustBeWritable): array {
		if ($path === '') {
			return $this->row($label, $source, $path, 'file', $required ? 'error' : 'info', $required ? 'Path is not configured.' : 'Optional file is not configured.');
		}

		if (!file_exists($path)) {
			return $this->row($label, $source, $path, 'file', 'error', 'File does not exist.');
		}

		if (!is_file($path)) {
			return $this->row($label, $source, $path, 'file', 'error', 'Path exists, but is not a file.');
		}

		if ($mustBeReadable && !is_readable($path)) {
			return $this->row($label, $source, $path, 'file', 'error', 'File is not readable.', $this->getPathMeta($path));
		}

		if ($mustBeWritable && !is_writable($path)) {
			return $this->row($label, $source, $path, 'file', 'warning', 'File is not writable.', $this->getPathMeta($path));
		}

		return $this->row($label, $source, $path, 'file', 'ok', 'File is available.', $this->getPathMeta($path));
	}

	private function checkExecutable(string $label, string $source, string $path, bool $required): array {
		if ($path === '') {
			return $this->row($label, $source, $path, 'executable', $required ? 'error' : 'info', $required ? 'Executable is not configured.' : 'Optional executable is not configured.');
		}

		if (!file_exists($path)) {
			return $this->row($label, $source, $path, 'executable', 'warning', 'Executable does not exist at configured path.');
		}

		if (!is_file($path)) {
			return $this->row($label, $source, $path, 'executable', 'warning', 'Configured path is not a file.');
		}

		if (!is_executable($path)) {
			return $this->row($label, $source, $path, 'executable', 'warning', 'File is not executable.', $this->getPathMeta($path));
		}

		return $this->row($label, $source, $path, 'executable', 'ok', 'Executable is available.', $this->getPathMeta($path));
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
				'label' => 'Permissions',
				'value' => substr(sprintf('%o', $perms), -4),
			];
		}

		$owner = fileowner($path);
		if ($owner !== false) {
			$meta[] = [
				'label' => 'Owner UID',
				'value' => (string)$owner,
			];
		}

		$group = filegroup($path);
		if ($group !== false) {
			$meta[] = [
				'label' => 'Group GID',
				'value' => (string)$group,
			];
		}

		$mtime = filemtime($path);
		if ($mtime !== false) {
			$meta[] = [
				'label' => 'Modified',
				'value' => date('Y-m-d H:i:s', $mtime),
			];
		}

		if (is_file($path)) {
			$size = filesize($path);
			if ($size !== false) {
				$meta[] = [
					'label' => 'Size',
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
}
