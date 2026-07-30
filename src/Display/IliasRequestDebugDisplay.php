<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use ilCtrl;

final class IliasRequestDebugDisplay implements IDisplay {

	private array $translations = [];

	public function __construct(
		private readonly IMvcView $view,
		private readonly ilCtrl $ilCtrl
	) {}

	public static function getName(): string {
		return 'iliasrequestdebugdisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		$this->loadTranslations();

		return $this->t('help', 'ILIAS request and controller debug overview.');
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->loadTranslations();
		$this->view->setTemplate('Display/IliasRequestDebugDisplay.php');

		$this->view->assign('generatedAt', date('c'));
		$this->view->assign('controllerRows', $this->getControllerRows());
		$this->view->assign('requestRows', $this->getRequestRows());
		$this->view->assign('getRows', $this->getGetRows());
		$this->view->assign('postRows', $this->getPostRows());
		$this->view->assign('serverRows', $this->getServerRows());
		$this->view->assign('translations', $this->translations);

		return $this->view->loadTemplate();
	}

	private function getControllerRows(): array {
		return [
			$this->row($this->t('command', 'Command'), 'ilCtrl::getCmd()', $this->ilCtrl->getCmd()),
			$this->row($this->t('command_class', 'Command class'), 'ilCtrl::getCmdClass()', $this->ilCtrl->getCmdClass()),
			$this->row($this->t('next_class', 'Next class'), 'ilCtrl::getNextClass()', $this->ilCtrl->getNextClass()),
			$this->row($this->t('context_object_id', 'Context object ID'), 'ilCtrl::getContextObjId()', $this->ilCtrl->getContextObjId()),
			$this->row($this->t('context_object_type', 'Context object type'), 'ilCtrl::getContextObjType()', $this->ilCtrl->getContextObjType()),
			$this->row($this->t('async_request', 'Asynchronous request'), 'ilCtrl::isAsynch()', $this->ilCtrl->isAsynch() ? $this->t('value_yes', 'yes') : $this->t('value_no', 'no')),
			$this->row($this->t('redirect_source', 'Redirect source'), 'ilCtrl::getRedirectSource()', $this->ilCtrl->getRedirectSource()),
			$this->row($this->t('current_class_path', 'Current class path'), 'ilCtrl::getCurrentClassPath()', $this->joinList($this->ilCtrl->getCurrentClassPath())),
			$this->row($this->t('call_history', 'Call history'), 'ilCtrl::getCallHistory()', $this->formatValue($this->ilCtrl->getCallHistory())),
		];
	}

	private function getRequestRows(): array {
		return [
			$this->row($this->t('request_method', 'Request method'), 'REQUEST_METHOD', $this->serverValue('REQUEST_METHOD')),
			$this->row($this->t('request_uri', 'Request URI'), 'REQUEST_URI', $this->serverValue('REQUEST_URI')),
			$this->row($this->t('script_name', 'Script name'), 'SCRIPT_NAME', $this->serverValue('SCRIPT_NAME')),
			$this->row($this->t('php_self', 'PHP self'), 'PHP_SELF', $this->serverValue('PHP_SELF')),
			$this->row($this->t('query_string', 'Query string'), 'QUERY_STRING', $this->serverValue('QUERY_STRING')),
			$this->row($this->t('http_host', 'HTTP host'), 'HTTP_HOST', $this->serverValue('HTTP_HOST')),
			$this->row($this->t('https', 'HTTPS'), 'HTTPS', $this->serverValue('HTTPS')),
			$this->row($this->t('remote_address', 'Remote address'), 'REMOTE_ADDR', $this->serverValue('REMOTE_ADDR')),
			$this->row($this->t('user_agent', 'User agent'), 'HTTP_USER_AGENT', $this->serverValue('HTTP_USER_AGENT')),
		];
	}

	private function getGetRows(): array {
		$params = [];
		parse_str($this->serverValue('QUERY_STRING'), $params);

		return $this->paramsToRows($this->filterSensitiveParams($params));
	}

	private function getPostRows(): array {
		$params = filter_input_array(INPUT_POST);

		if (!is_array($params)) {
			$params = [];
		}

		return $this->paramsToRows($this->filterSensitiveParams($params));
	}

	private function getServerRows(): array {
		$keys = [
			'REQUEST_TIME',
			'REQUEST_TIME_FLOAT',
			'SERVER_NAME',
			'SERVER_PORT',
			'SERVER_PROTOCOL',
			'REQUEST_SCHEME',
			'DOCUMENT_ROOT',
			'SCRIPT_FILENAME',
			'PATH_INFO',
			'HTTP_ACCEPT',
			'HTTP_ACCEPT_LANGUAGE',
			'HTTP_REFERER',
			'HTTP_X_REQUESTED_WITH',
			'CONTENT_TYPE',
			'CONTENT_LENGTH',
		];

		$rows = [];

		foreach ($keys as $key) {
			$value = $this->serverValue($key);

			if ($value === '') {
				continue;
			}

			$rows[] = [
				'key' => $key,
				'value' => $value,
			];
		}

		return $rows;
	}

	private function paramsToRows(array $params): array {
		ksort($params);

		$rows = [];

		foreach ($params as $key => $value) {
			$rows[] = [
				'key' => (string)$key,
				'value' => $this->formatValue($value),
			];
		}

		return $rows;
	}

	private function filterSensitiveParams(array $params): array {
		$filtered = [];

		foreach ($params as $key => $value) {
			$key = (string)$key;

			if ($this->isSensitiveKey($key)) {
				$filtered[$key] = '************';
				continue;
			}

			if (is_array($value)) {
				$filtered[$key] = $this->filterSensitiveParams($value);
				continue;
			}

			$filtered[$key] = $value;
		}

		return $filtered;
	}

	private function isSensitiveKey(string $key): bool {
		$key = strtolower($key);

		return str_contains($key, 'pass')
			|| str_contains($key, 'pwd')
			|| str_contains($key, 'token')
			|| str_contains($key, 'secret')
			|| str_contains($key, 'csrf');
	}

	private function row(string $label, string $key, mixed $value): array {
		return [
			'label' => $label,
			'key' => $key,
			'value' => $this->formatValue($value),
		];
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

	private function joinList(array $items): string {
		if (empty($items)) {
			return '';
		}

		return implode(' → ', array_map(static fn($item): string => (string)$item, $items));
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

			return json_encode($this->normalizeValue($value), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
		}

		return get_debug_type($value);
	}

	private function normalizeValue(mixed $value, int $depth = 0): mixed {
		if ($depth > 5) {
			return '...';
		}

		if ($value === null || is_scalar($value)) {
			return $value;
		}

		if (is_array($value)) {
			$out = [];

			foreach ($value as $key => $item) {
				$out[(string)$key] = $this->normalizeValue($item, $depth + 1);
			}

			return $out;
		}

		return get_debug_type($value);
	}

	private function loadTranslations(): void {
		$this->view->setPath(\DIR_COMPONENTS . 'Base3/Base3Ilias');
		$this->view->loadBricks('Display');

		$common = $this->view->getBricks('base3ilias_common');
		$specific = $this->view->getBricks('ilias_request_debug_display');

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
