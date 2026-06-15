<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use ilIniFile;

final class IliasLogAdminDisplay implements IDisplay {

	private const DEFAULT_NUM = 100;
	private const MAX_NUM = 1000;
	private const READ_BLOCK_SIZE = 8192;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly ILinkTargetService $linkTargetService,
		private readonly ilIniFile $ilIliasIniFile
	) {}

	public static function getName(): string {
		return 'iliaslogadmindisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		return 'ILIAS log viewer with auto-refresh.';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$out = strtolower((string)$out);

		if ($out === 'json') {
			return $this->handleJson();
		}

		return $this->handleHtml();
	}

	private function handleHtml(): string {
		$this->view->setPath(\DIR_COMPONENTS . 'Base3/Base3Ilias');
		$this->view->setTemplate('Display/IliasLogAdminDisplay.php');

		$this->view->assign('endpoint', $this->buildEndpointBase());
		$this->view->assign('logPath', $this->getLogFullPath());
		$this->view->assign('defaultNum', self::DEFAULT_NUM);
		$this->view->assign('maxNum', self::MAX_NUM);

		return $this->view->loadTemplate();
	}

	private function handleJson(): string {
		$action = (string)($this->request->get('action') ?? '');

		try {
			return match ($action) {
				'tail' => $this->jsonSuccess($this->loadTail()),
				default => $this->jsonError("Unknown action '$action'. Use: tail"),
			};
		} catch (\Throwable $e) {
			return $this->jsonError('Exception: ' . $e->getMessage());
		}
	}

	private function loadTail(): array {
		$logFullPath = $this->getLogFullPath();

		$num = (int)($this->request->get('num') ?? self::DEFAULT_NUM);
		$num = max(1, min(self::MAX_NUM, $num));

		if ($logFullPath === '') {
			return [
				'path' => '',
				'num' => $num,
				'readable' => false,
				'message' => 'ILIAS log path is not configured.',
				'logs' => [],
			];
		}

		if (!is_file($logFullPath)) {
			return [
				'path' => $logFullPath,
				'num' => $num,
				'readable' => false,
				'message' => 'ILIAS log file does not exist.',
				'logs' => [],
			];
		}

		if (!is_readable($logFullPath)) {
			return [
				'path' => $logFullPath,
				'num' => $num,
				'readable' => false,
				'message' => 'ILIAS log file is not readable.',
				'logs' => [],
			];
		}

		$lines = $this->readTailLines($logFullPath, $num);
		$lines = array_reverse($lines);

		$logs = [];
		foreach ($lines as $line) {
			$logs[] = $this->parseLogLine($line);
		}

		return [
			'path' => $logFullPath,
			'num' => $num,
			'readable' => true,
			'size' => filesize($logFullPath) ?: 0,
			'mtime' => filemtime($logFullPath) ? date('c', (int)filemtime($logFullPath)) : '',
			'logs' => $logs,
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

	private function readTailLines(string $file, int $num): array {
		$handle = fopen($file, 'rb');

		if ($handle === false) {
			return [];
		}

		try {
			$position = filesize($file);
			if ($position === false || $position <= 0) {
				return [];
			}

			$buffer = '';
			$lines = [];

			while ($position > 0 && count($lines) <= $num) {
				$readSize = min(self::READ_BLOCK_SIZE, $position);
				$position -= $readSize;

				fseek($handle, $position);
				$chunk = fread($handle, $readSize);

				if ($chunk === false || $chunk === '') {
					break;
				}

				$buffer = $chunk . $buffer;
				$lines = explode("\n", $buffer);
			}

			$out = [];
			foreach ($lines as $line) {
				$line = rtrim($line, "\r\n");

				if ($line === '') {
					continue;
				}

				$out[] = $line;
			}

			if (count($out) > $num) {
				$out = array_slice($out, -$num);
			}

			return $out;
		} finally {
			fclose($handle);
		}
	}

	private function parseLogLine(string $line): array {
		$matches = [];

		if (preg_match('/^\[(?<request>[^\]]*)\]\s+\[(?<timestamp>[^\]]*)\]\s+(?<channel>[^:\s]+)\.(?<level>[A-Z]+):\s+(?<message>.*)$/', $line, $matches) === 1) {
			return [
				'request' => (string)$matches['request'],
				'timestamp' => (string)$matches['timestamp'],
				'channel' => (string)$matches['channel'],
				'level' => strtolower((string)$matches['level']),
				'message' => (string)$matches['message'],
				'raw' => $line,
			];
		}

		return [
			'request' => '',
			'timestamp' => '',
			'channel' => '',
			'level' => '',
			'message' => $line,
			'raw' => $line,
		];
	}

	private function buildEndpointBase(): string {
		return $this->linkTargetService->getLink(
			[
				'name' => self::getName(),
				'out' => 'json'
			],
			[
				'action' => ''
			]
		);
	}

	private function jsonSuccess(array $data): string {
		return json_encode([
			'status' => 'ok',
			'timestamp' => gmdate('c'),
			'data' => $data
		], JSON_UNESCAPED_UNICODE);
	}

	private function jsonError(string $message): string {
		return json_encode([
			'status' => 'error',
			'timestamp' => gmdate('c'),
			'message' => $message
		], JSON_UNESCAPED_UNICODE);
	}
}
