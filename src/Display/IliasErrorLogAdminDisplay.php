<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use ilIniFile;

final class IliasErrorLogAdminDisplay implements IDisplay {

	private const DEFAULT_NUM = 100;
	private const MAX_FILES = 500;
	private const MAX_READ_BYTES = 1048576;
	private const READ_BLOCK_SIZE = 8192;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly ILinkTargetService $linkTargetService,
		private readonly ilIniFile $ilIliasIniFile
	) {}

	public static function getName(): string {
		return 'iliaserrorlogadmindisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getHelp(): string {
		return 'ILIAS error log file viewer with auto-refresh.';
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
		$this->view->setTemplate('Display/IliasErrorLogAdminDisplay.php');

		$this->view->assign('endpoint', $this->buildEndpointBase());
		$this->view->assign('errorPath', $this->getErrorPath());
		$this->view->assign('defaultNum', self::DEFAULT_NUM);
		$this->view->assign('maxFiles', self::MAX_FILES);

		return $this->view->loadTemplate();
	}

	private function handleJson(): string {
		$action = (string)($this->request->get('action') ?? '');

		try {
			return match ($action) {
				'list' => $this->jsonSuccess($this->loadList()),
				'read' => $this->jsonSuccess($this->loadFile()),
				default => $this->jsonError("Unknown action '$action'. Use: list, read"),
			};
		} catch (\Throwable $e) {
			return $this->jsonError('Exception: ' . $e->getMessage());
		}
	}

	private function loadList(): array {
		$errorPath = $this->getErrorPath();
		$num = (int)($this->request->get('num') ?? self::DEFAULT_NUM);
		$num = max(1, min(self::MAX_FILES, $num));

		if ($errorPath === '') {
			return [
				'path' => '',
				'num' => $num,
				'readable' => false,
				'message' => 'ILIAS error log path is not configured.',
				'files' => [],
			];
		}

		if (!is_dir($errorPath)) {
			return [
				'path' => $errorPath,
				'num' => $num,
				'readable' => false,
				'message' => 'ILIAS error log path does not exist or is not a directory.',
				'files' => [],
			];
		}

		if (!is_readable($errorPath)) {
			return [
				'path' => $errorPath,
				'num' => $num,
				'readable' => false,
				'message' => 'ILIAS error log path is not readable.',
				'files' => [],
			];
		}

		return [
			'path' => $errorPath,
			'num' => $num,
			'readable' => true,
			'message' => '',
			'files' => array_slice($this->getFiles($errorPath), 0, $num),
		];
	}

	private function loadFile(): array {
		$errorPath = $this->getErrorPath();
		$file = basename((string)($this->request->get('file') ?? ''));

		if ($errorPath === '') {
			return [
				'path' => '',
				'file' => $file,
				'readable' => false,
				'message' => 'ILIAS error log path is not configured.',
				'content' => '',
			];
		}

		if ($file === '') {
			return [
				'path' => $errorPath,
				'file' => '',
				'readable' => false,
				'message' => 'No file selected.',
				'content' => '',
			];
		}

		$fullPath = $this->joinPath($errorPath, $file);

		if (!$this->isSafeFile($errorPath, $fullPath)) {
			return [
				'path' => $errorPath,
				'file' => $file,
				'readable' => false,
				'message' => 'Invalid file selection.',
				'content' => '',
			];
		}

		if (!is_file($fullPath)) {
			return [
				'path' => $errorPath,
				'file' => $file,
				'readable' => false,
				'message' => 'Selected file does not exist.',
				'content' => '',
			];
		}

		if (!is_readable($fullPath)) {
			return [
				'path' => $errorPath,
				'file' => $file,
				'readable' => false,
				'message' => 'Selected file is not readable.',
				'content' => '',
			];
		}

		$size = filesize($fullPath) ?: 0;
		$content = $this->readFileEnd($fullPath, self::MAX_READ_BYTES);
		$truncated = $size > self::MAX_READ_BYTES;

		return [
			'path' => $errorPath,
			'file' => $file,
			'readable' => true,
			'message' => '',
			'size' => $size,
			'size_formatted' => $this->formatBytes((int)$size),
			'read_bytes' => strlen($content),
			'read_bytes_formatted' => $this->formatBytes(strlen($content)),
			'mtime' => filemtime($fullPath) ? date('c', (int)filemtime($fullPath)) : '',
			'truncated' => $truncated,
			'content' => $content,
		];
	}

	private function getErrorPath(): string {
		return trim((string)$this->ilIliasIniFile->readVariable('log', 'error_path'));
	}

	private function getFiles(string $errorPath): array {
		$items = scandir($errorPath);

		if ($items === false) {
			return [];
		}

		$files = [];

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			if (str_contains($item, '/') || str_contains($item, '\\')) {
				continue;
			}

			$fullPath = $this->joinPath($errorPath, $item);

			if (!is_file($fullPath)) {
				continue;
			}

			$mtime = filemtime($fullPath) ?: 0;
			$size = filesize($fullPath) ?: 0;

			$files[] = [
				'name' => $item,
				'size' => $size,
				'size_formatted' => $this->formatBytes((int)$size),
				'mtime' => $mtime > 0 ? date('c', $mtime) : '',
				'mtime_sort' => $mtime,
				'readable' => is_readable($fullPath),
			];
		}

		usort($files, static function(array $a, array $b): int {
			$diff = ((int)$b['mtime_sort']) <=> ((int)$a['mtime_sort']);

			if ($diff !== 0) {
				return $diff;
			}

			return strcmp((string)$a['name'], (string)$b['name']);
		});

		foreach ($files as &$file) {
			unset($file['mtime_sort']);
		}

		return $files;
	}

	private function isSafeFile(string $errorPath, string $fullPath): bool {
		$realBase = realpath($errorPath);
		$realFile = realpath($fullPath);

		if ($realBase === false || $realFile === false) {
			return false;
		}

		return str_starts_with($realFile, rtrim($realBase, '/\\') . DIRECTORY_SEPARATOR);
	}

	private function readFileEnd(string $file, int $maxBytes): string {
		$handle = fopen($file, 'rb');

		if ($handle === false) {
			return '';
		}

		try {
			$size = filesize($file);

			if ($size === false || $size <= 0) {
				return '';
			}

			$readBytes = min($maxBytes, $size);
			$position = $size - $readBytes;

			fseek($handle, $position);

			$content = '';
			while (!feof($handle) && strlen($content) < $readBytes) {
				$chunk = fread($handle, min(self::READ_BLOCK_SIZE, $readBytes - strlen($content)));

				if ($chunk === false || $chunk === '') {
					break;
				}

				$content .= $chunk;
			}

			return $content;
		} finally {
			fclose($handle);
		}
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
