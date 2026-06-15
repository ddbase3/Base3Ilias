<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use ilIniFile;

final class IliasConfigAdminDisplay implements IDisplay {

	public function __construct(
		private readonly IMvcView $view,
		private readonly ilIniFile $ilIliasIniFile
	) {}

	public static function getName(): string {
		return 'iliasconfigadmindisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		return 'ILIAS configuration overview.';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->view->setPath(\DIR_COMPONENTS . 'Base3/Base3Ilias');
		$this->view->setTemplate('Display/IliasConfigAdminDisplay.php');

		$this->view->assign('sections', $this->getSections());
		$this->view->assign('generatedAt', date('c'));

		return $this->view->loadTemplate();
	}

	private function getSections(): array {
		return [
			[
				'title' => 'Server',
				'description' => 'Grundlegende ILIAS-Pfade und Server-Einstellungen.',
				'rows' => [
					$this->row('server', 'http_path', 'HTTP Path'),
					$this->row('server', 'absolute_path', 'Absolute Path'),
					$this->row('server', 'presetting', 'Presetting'),
					$this->row('server', 'timezone', 'Timezone'),
				],
			],
			[
				'title' => 'Clients',
				'description' => 'Client-Konfiguration und Datenverzeichnisse.',
				'rows' => [
					$this->row('clients', 'path', 'Client Path'),
					$this->row('clients', 'inifile', 'Client Ini File'),
					$this->row('clients', 'datadir', 'Data Directory'),
					$this->row('clients', 'default', 'Default Client'),
					$this->row('clients', 'list', 'Client List Enabled'),
				],
			],
			[
				'title' => 'Log',
				'description' => 'ILIAS Logging-Konfiguration aus der globalen Ini.',
				'rows' => [
					$this->row('log', 'enabled', 'Enabled'),
					$this->row('log', 'level', 'Level'),
					$this->row('log', 'path', 'Log Path'),
					$this->row('log', 'file', 'Log File'),
					[
						'section' => 'log',
						'key' => 'path + file',
						'label' => 'Full Log Path',
						'value' => $this->getLogFullPath(),
						'empty' => $this->getLogFullPath() === '',
						'sensitive' => false,
					],
					$this->row('log', 'error_path', 'Error Path'),
				],
			],
			[
				'title' => 'Tools',
				'description' => 'Externe Programme, die ILIAS verwenden kann.',
				'rows' => [
					$this->row('tools', 'convert', 'Convert'),
					$this->row('tools', 'zip', 'Zip'),
					$this->row('tools', 'unzip', 'Unzip'),
					$this->row('tools', 'java', 'Java'),
					$this->row('tools', 'htmldoc', 'HTMLDoc'),
					$this->row('tools', 'ffmpeg', 'FFmpeg'),
					$this->row('tools', 'ghostscript', 'Ghostscript'),
					$this->row('tools', 'latex', 'LaTeX'),
					$this->row('tools', 'vscantype', 'Virus Scan Type'),
					$this->row('tools', 'scancommand', 'Scan Command'),
					$this->row('tools', 'cleancommand', 'Clean Command'),
					$this->row('tools', 'fop', 'FOP'),
					$this->row('tools', 'lessc', 'Less Compiler'),
					$this->row('tools', 'enable_system_styles_management', 'System Styles Management'),
					$this->row('tools', 'phantomjs', 'PhantomJS'),
				],
			],
			[
				'title' => 'HTTPS',
				'description' => 'Automatische HTTPS-Erkennung.',
				'rows' => [
					$this->row('https', 'auto_https_detect_enabled', 'Auto HTTPS Detect Enabled'),
					$this->row('https', 'auto_https_detect_header_name', 'Header Name'),
					$this->row('https', 'auto_https_detect_header_value', 'Header Value'),
				],
			],
			[
				'title' => 'Distribution Defaults',
				'description' => 'Distributionsspezifische Pfad-Vorgaben aus der Ini.',
				'rows' => [
					$this->row('debian', 'data_dir', 'Debian Data Directory'),
					$this->row('debian', 'log', 'Debian Log'),
					$this->row('redhat', 'data_dir', 'RedHat Data Directory'),
					$this->row('redhat', 'log', 'RedHat Log'),
					$this->row('suse', 'data_dir', 'SUSE Data Directory'),
					$this->row('suse', 'log', 'SUSE Log'),
				],
			],
			[
				'title' => 'Setup',
				'description' => 'Sensible Werte werden nicht im Klartext angezeigt.',
				'rows' => [
					$this->row('setup', 'pass', 'Setup Password', true),
				],
			],
		];
	}

	private function row(string $section, string $key, string $label, bool $sensitive = false): array {
		$value = (string)$this->ilIliasIniFile->readVariable($section, $key);

		if ($sensitive) {
			$value = $this->maskValue($value);
		}

		return [
			'section' => $section,
			'key' => $key,
			'label' => $label,
			'value' => $value,
			'empty' => $value === '',
			'sensitive' => $sensitive,
		];
	}

	private function getLogFullPath(): string {
		$logPath = trim((string)$this->ilIliasIniFile->readVariable('log', 'path'));
		$logFile = trim((string)$this->ilIliasIniFile->readVariable('log', 'file'));

		if ($logPath === '' || $logFile === '') {
			return '';
		}

		return rtrim($logPath, '/\\') . '/' . ltrim($logFile, '/\\');
	}

	private function maskValue(string $value): string {
		if ($value === '') {
			return '';
		}

		return str_repeat('*', 12);
	}
}
