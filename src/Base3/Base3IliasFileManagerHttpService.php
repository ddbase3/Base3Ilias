<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use ILIAS\DI\Container;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class Base3IliasFileManagerHttpService {

	public const DEFAULT_MAX_FILE_SIZE = 50 * 1024 * 1024;

	public function __construct(
		private readonly Base3IliasManagedFileStorageService $storageService,
		private readonly Base3IliasFileUploadService $uploadService,
		private readonly Container $iliasContainer
	) {}

	public static function buildEndpoints(string $baseUrl, string $parameter = 'fm_action'): array {
		$actions = [
			'list' => 'list',
			'stat' => 'stat',
			'mkdir' => 'mkdir',
			'delete' => 'delete',
			'rmdir' => 'rmdir',
			'copy' => 'copy',
			'move' => 'move',
			'uploadStart' => 'uploadStart',
			'uploadChunk' => 'uploadChunk',
			'uploadStatus' => 'uploadStatus',
			'uploadFinish' => 'uploadFinish',
			'uploadAbort' => 'uploadAbort',
			'download' => 'download',
		];
		$endpoints = [];

		foreach ($actions as $key => $action) {
			$separator = str_contains($baseUrl, '?') ? '&' : '?';
			$endpoints[$key] = $baseUrl . $separator . rawurlencode($parameter) . '=' . rawurlencode($action);
		}

		return $endpoints;
	}

	public function handle(
		string $action,
		string $ownerGroup,
		string $ownerName,
		string $mode,
		int $userId,
		int $maxFileSize
	): void {
		if ($action === 'download') {
			$this->download($ownerGroup, $ownerName, $mode);
			return;
		}

		$this->respondJson(function() use ($action, $ownerGroup, $ownerName, $mode, $userId, $maxFileSize): array {
			return match ($action) {
				'list' => $this->list($ownerGroup, $ownerName, $mode),
				'stat' => $this->stat($ownerGroup, $ownerName, $mode),
				'mkdir' => $this->mkdir($ownerGroup, $ownerName, $mode),
				'delete' => $this->delete($ownerGroup, $ownerName, $mode),
				'rmdir' => $this->rmdir($ownerGroup, $ownerName, $mode),
				'copy' => $this->copy($ownerGroup, $ownerName, $mode),
				'move' => $this->move($ownerGroup, $ownerName, $mode),
				'uploadStart' => $this->uploadService->start($ownerGroup, $ownerName, $mode, $userId, $maxFileSize, $this->jsonBody()),
				'uploadChunk' => $this->uploadService->saveChunk($ownerGroup, $ownerName, $mode, $userId, $this->parsedPostBody(), $_FILES),
				'uploadStatus' => $this->uploadStatus($ownerGroup, $ownerName, $mode, $userId),
				'uploadFinish' => $this->uploadFinish($ownerGroup, $ownerName, $mode, $userId),
				'uploadAbort' => $this->uploadAbort($ownerGroup, $ownerName, $mode, $userId),
				default => throw new InvalidArgumentException('Unknown FileManager action.'),
			};
		});
	}

	private function list(string $ownerGroup, string $ownerName, string $mode): array {
		$body = $this->jsonBody();
		$path = Base3IliasManagedFileStorageService::normalizePath((string)($body['path'] ?? ''));

		return [
			'items' => $this->storageService->listDescriptors($ownerGroup, $ownerName, $mode, $path),
			'path' => $path,
			'containerId' => $this->storageService->getOrCreateStorageIdentifier($ownerGroup, $ownerName, $mode),
		];
	}

	private function stat(string $ownerGroup, string $ownerName, string $mode): array {
		$body = $this->jsonBody();
		$descriptor = $this->storageService->statDescriptor($ownerGroup, $ownerName, $mode, (string)($body['path'] ?? ''));

		if ($descriptor === null) {
			http_response_code(404);
			throw new RuntimeException('File or directory not found.');
		}

		return $descriptor;
	}

	private function mkdir(string $ownerGroup, string $ownerName, string $mode): array {
		$body = $this->jsonBody();
		$path = Base3IliasManagedFileStorageService::normalizePath((string)($body['path'] ?? ''));
		$storage = $this->storageService->openOrCreate($ownerGroup, $ownerName, $mode);

		if ($path === '' || !$storage->mkdir($path)) {
			throw new RuntimeException('Directory could not be created.');
		}

		return ['success' => true];
	}

	private function delete(string $ownerGroup, string $ownerName, string $mode): array {
		$body = $this->jsonBody();
		$path = Base3IliasManagedFileStorageService::normalizePath((string)($body['path'] ?? ''));
		$storage = $this->storageService->openOrCreate($ownerGroup, $ownerName, $mode);

		if ($path === '' || !$storage->delete($path)) {
			throw new RuntimeException('File could not be deleted.');
		}

		return ['success' => true];
	}

	private function rmdir(string $ownerGroup, string $ownerName, string $mode): array {
		$body = $this->jsonBody();
		$path = Base3IliasManagedFileStorageService::normalizePath((string)($body['path'] ?? ''));
		$storage = $this->storageService->openOrCreate($ownerGroup, $ownerName, $mode);

		if ($path === '' || !$storage->rmdir($path)) {
			throw new RuntimeException('Directory could not be deleted.');
		}

		return ['success' => true];
	}

	private function copy(string $ownerGroup, string $ownerName, string $mode): array {
		$body = $this->jsonBody();
		$source = Base3IliasManagedFileStorageService::normalizePath((string)($body['sourcePath'] ?? ''));
		$target = Base3IliasManagedFileStorageService::normalizePath((string)($body['targetPath'] ?? ''));
		$storage = $this->storageService->openOrCreate($ownerGroup, $ownerName, $mode);

		if ($source === '' || $target === '' || !$storage->copy($source, $target)) {
			throw new RuntimeException('File could not be copied.');
		}

		return ['success' => true];
	}

	private function move(string $ownerGroup, string $ownerName, string $mode): array {
		$body = $this->jsonBody();
		$source = Base3IliasManagedFileStorageService::normalizePath((string)($body['sourcePath'] ?? ''));
		$target = Base3IliasManagedFileStorageService::normalizePath((string)($body['targetPath'] ?? ''));
		$storage = $this->storageService->openOrCreate($ownerGroup, $ownerName, $mode);

		if ($source === '' || $target === '' || !$storage->move($source, $target)) {
			throw new RuntimeException('File could not be moved.');
		}

		return ['success' => true];
	}

	private function uploadStatus(string $ownerGroup, string $ownerName, string $mode, int $userId): array {
		$body = $this->jsonBody();
		return $this->uploadService->status($ownerGroup, $ownerName, $mode, $userId, trim((string)($body['uploadId'] ?? '')));
	}

	private function uploadFinish(string $ownerGroup, string $ownerName, string $mode, int $userId): array {
		$body = $this->jsonBody();
		return $this->uploadService->finish($ownerGroup, $ownerName, $mode, $userId, trim((string)($body['uploadId'] ?? '')));
	}

	private function uploadAbort(string $ownerGroup, string $ownerName, string $mode, int $userId): array {
		$body = $this->jsonBody();
		return $this->uploadService->abort($ownerGroup, $ownerName, $mode, $userId, trim((string)($body['uploadId'] ?? '')));
	}

	private function download(string $ownerGroup, string $ownerName, string $mode): void {
		$query = $this->iliasContainer->http()->request()->getQueryParams();
		$path = Base3IliasManagedFileStorageService::normalizePath((string)($query['path'] ?? ''));
		if ($path === '') {
			http_response_code(404);
			return;
		}

		$storage = $this->storageService->openOrCreate($ownerGroup, $ownerName, $mode);
		$stat = $storage->stat($path);
		if ($stat === null || (string)($stat['type'] ?? '') !== 'file') {
			http_response_code(404);
			return;
		}

		$content = $storage->read($path);
		$name = basename($path);
		$fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'download';

		header('Content-Type: application/octet-stream');
		header('Content-Length: ' . strlen($content));
		header('Content-Disposition: attachment; filename="' . $fallbackName . '"; filename*=UTF-8\'\'' . rawurlencode($name));
		header('X-Content-Type-Options: nosniff');
		echo $content;
		exit;
	}

	private function parsedPostBody(): array {
		$body = $this->iliasContainer->http()->request()->getParsedBody();
		if ($body === null) {
			return [];
		}
		if (!is_array($body)) {
			throw new InvalidArgumentException('POST request body must be an array.');
		}
		return $body;
	}

	private function jsonBody(): array {
		$content = file_get_contents('php://input');
		if ($content === false || trim($content) === '') {
			return [];
		}

		$data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($data)) {
			throw new InvalidArgumentException('JSON request body must be an object.');
		}
		return $data;
	}

	private function respondJson(callable $handler): void {
		try {
			$payload = $handler();
			$status = http_response_code();
			if ($status < 200 || $status >= 600) {
				http_response_code(200);
			}
		}
		catch (InvalidArgumentException|JsonException $error) {
			http_response_code(400);
			$payload = ['message' => $error->getMessage()];
		}
		catch (Throwable $error) {
			if (http_response_code() < 400) {
				http_response_code(500);
			}
			$payload = ['message' => $error->getMessage()];
		}

		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
		exit;
	}
}
