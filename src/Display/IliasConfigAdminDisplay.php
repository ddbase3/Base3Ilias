<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use ilIniFile;

final class IliasConfigAdminDisplay implements IDisplay {

	private array $translations = [];

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
		$this->loadTranslations();

		return $this->t('help', 'ILIAS configuration overview.');
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->loadTranslations();
		$this->view->setTemplate('Display/IliasConfigAdminDisplay.php');

		$this->view->assign('sections', $this->getSections());
		$this->view->assign('generatedAt', date('c'));
		$this->view->assign('translations', $this->translations);

		return $this->view->loadTemplate();
	}

	private function getSections(): array {
		return [
			[
				'title' => $this->t('section_server_title', 'Server'),
				'description' => $this->t('section_server_description', 'Basic ILIAS paths and server settings.'),
				'rows' => [
					$this->row('server', 'http_path', $this->t('http_path', 'HTTP path')),
					$this->row('server', 'absolute_path', $this->t('absolute_path', 'Absolute path')),
					$this->row('server', 'presetting', $this->t('presetting', 'Presetting')),
					$this->row('server', 'timezone', $this->t('timezone', 'Time zone')),
				],
			],
			[
				'title' => $this->t('section_clients_title', 'Clients'),
				'description' => $this->t('section_clients_description', 'Client configuration and data directories.'),
				'rows' => [
					$this->row('clients', 'path', $this->t('client_path', 'Client path')),
					$this->row('clients', 'inifile', $this->t('client_ini_file', 'Client INI file')),
					$this->row('clients', 'datadir', $this->t('data_directory', 'Data directory')),
					$this->row('clients', 'default', $this->t('default_client', 'Default client')),
					$this->row('clients', 'list', $this->t('client_list_enabled', 'Client list enabled')),
				],
			],
			[
				'title' => $this->t('section_log_title', 'Log'),
				'description' => $this->t('section_log_description', 'ILIAS logging configuration from the global INI file.'),
				'rows' => [
					$this->row('log', 'enabled', $this->t('enabled', 'Enabled')),
					$this->row('log', 'level', $this->t('level', 'Level')),
					$this->row('log', 'path', $this->t('log_path', 'Log path')),
					$this->row('log', 'file', $this->t('log_file', 'Log file')),
					[
						'section' => 'log',
						'key' => 'path + file',
						'label' => $this->t('full_log_path', 'Full log path'),
						'value' => $this->getLogFullPath(),
						'empty' => $this->getLogFullPath() === '',
						'sensitive' => false,
					],
					$this->row('log', 'error_path', $this->t('error_path', 'Error path')),
				],
			],
			[
				'title' => $this->t('section_tools_title', 'Tools'),
				'description' => $this->t('section_tools_description', 'External programs that ILIAS can use.'),
				'rows' => [
					$this->row('tools', 'convert', $this->t('convert', 'Convert')),
					$this->row('tools', 'zip', $this->t('zip', 'Zip')),
					$this->row('tools', 'unzip', $this->t('unzip', 'Unzip')),
					$this->row('tools', 'java', $this->t('java', 'Java')),
					$this->row('tools', 'htmldoc', $this->t('htmldoc', 'HTMLDoc')),
					$this->row('tools', 'ffmpeg', $this->t('ffmpeg', 'FFmpeg')),
					$this->row('tools', 'ghostscript', $this->t('ghostscript', 'Ghostscript')),
					$this->row('tools', 'latex', $this->t('latex', 'LaTeX')),
					$this->row('tools', 'vscantype', $this->t('virus_scan_type', 'Virus scan type')),
					$this->row('tools', 'scancommand', $this->t('scan_command', 'Scan command')),
					$this->row('tools', 'cleancommand', $this->t('clean_command', 'Clean command')),
					$this->row('tools', 'fop', $this->t('fop', 'FOP')),
					$this->row('tools', 'lessc', $this->t('less_compiler', 'Less compiler')),
					$this->row('tools', 'enable_system_styles_management', $this->t('system_styles_management', 'System styles management')),
					$this->row('tools', 'phantomjs', $this->t('phantomjs', 'PhantomJS')),
				],
			],
			[
				'title' => $this->t('section_https_title', 'HTTPS'),
				'description' => $this->t('section_https_description', 'Automatic HTTPS detection.'),
				'rows' => [
					$this->row('https', 'auto_https_detect_enabled', $this->t('auto_https_detect_enabled', 'Automatic HTTPS detection enabled')),
					$this->row('https', 'auto_https_detect_header_name', $this->t('header_name', 'Header name')),
					$this->row('https', 'auto_https_detect_header_value', $this->t('header_value', 'Header value')),
				],
			],
			[
				'title' => $this->t('section_distribution_title', 'Distribution defaults'),
				'description' => $this->t('section_distribution_description', 'Distribution-specific path defaults from the INI file.'),
				'rows' => [
					$this->row('debian', 'data_dir', $this->t('debian_data_directory', 'Debian data directory')),
					$this->row('debian', 'log', $this->t('debian_log', 'Debian log')),
					$this->row('redhat', 'data_dir', $this->t('redhat_data_directory', 'Red Hat data directory')),
					$this->row('redhat', 'log', $this->t('redhat_log', 'Red Hat log')),
					$this->row('suse', 'data_dir', $this->t('suse_data_directory', 'SUSE data directory')),
					$this->row('suse', 'log', $this->t('suse_log', 'SUSE log')),
				],
			],
			[
				'title' => $this->t('section_setup_title', 'Setup'),
				'description' => $this->t('section_setup_description', 'Sensitive values are not displayed in plain text.'),
				'rows' => [
					$this->row('setup', 'pass', $this->t('setup_password', 'Setup password'), true),
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

	private function loadTranslations(): void {
		$this->view->setPath(\DIR_COMPONENTS . 'Base3/Base3Ilias');
		$this->view->loadBricks('Display');

		$common = $this->view->getBricks('base3ilias_common');
		$specific = $this->view->getBricks('ilias_config_admin_display');

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
