<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Event\Api\IEventManager;
use Base3\Settings\Api\ISettingsStore;
use ILIAS\DI\Container;
use ILIAS\Filesystem\Stream\Streams;
use InvalidArgumentException;
use ResourceFoundation\Api\IFileStorage;
use ResourceFoundation\Api\IFileStorageFactory;
use ResourceFoundation\Api\IManagedFileStorageService;
use ResourceFoundation\Event\ManagedFileStorageDeletingEvent;
use RuntimeException;
use Throwable;

final class Base3IliasManagedFileStorageService implements IManagedFileStorageService {

	private const SETTINGS_GROUP = 'base3ilias-filemanager';

	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly IFileStorageFactory $fileStorageFactory,
		private readonly Container $iliasContainer,
		private readonly IEventManager $eventManager
	) {}

	public function openOrCreate(string $ownerGroup, string $ownerName, string $mode): IFileStorage {
		return $this->fileStorageFactory->openStorage(
			$this->getOrCreateStorageIdentifier($ownerGroup, $ownerName, $mode),
			$mode
		);
	}

	public function getOrCreateStorageIdentifier(string $ownerGroup, string $ownerName, string $mode): string {
		[$ownerGroup, $ownerName, $mode] = $this->normalizeOwner($ownerGroup, $ownerName, $mode);
		$key = $this->settingsName($ownerGroup, $ownerName, $mode);
		$settings = $this->settingsStore->get(self::SETTINGS_GROUP, $key, []);
		$storageId = trim((string)($settings['storage_id'] ?? ''));

		if ($storageId !== '') {
			return $storageId;
		}

		$storageId = $this->createStorage($mode);

		try {
			$this->settingsStore->set(self::SETTINGS_GROUP, $key, [
				'owner_group' => $ownerGroup,
				'owner_name' => $ownerName,
				'mode' => $mode,
				'storage_id' => $storageId,
			]);
			$this->settingsStore->save();
		}
		catch (Throwable $error) {
			$this->removeStorage($storageId, $mode);
			throw $error;
		}

		return $storageId;
	}


	public function adoptStorageIdentifier(
		string $ownerGroup,
		string $ownerName,
		string $mode,
		string $storageId
	): void {
		[$ownerGroup, $ownerName, $mode] = $this->normalizeOwner($ownerGroup, $ownerName, $mode);
		$storageId = trim($storageId);
		if ($storageId === '') {
			throw new InvalidArgumentException('Managed file storage identifier is required.');
		}

		$key = $this->settingsName($ownerGroup, $ownerName, $mode);
		if ($this->settingsStore->has(self::SETTINGS_GROUP, $key)) {
			throw new RuntimeException('Managed file storage owner is already registered.');
		}

		$this->settingsStore->set(self::SETTINGS_GROUP, $key, [
			'owner_group' => $ownerGroup,
			'owner_name' => $ownerName,
			'mode' => $mode,
			'storage_id' => $storageId,
		]);
		$this->settingsStore->save();
	}

	public function has(string $ownerGroup, string $ownerName, string $mode): bool {
		[$ownerGroup, $ownerName, $mode] = $this->normalizeOwner($ownerGroup, $ownerName, $mode);
		$key = $this->settingsName($ownerGroup, $ownerName, $mode);
		$settings = $this->settingsStore->get(self::SETTINGS_GROUP, $key, []);

		return trim((string)($settings['storage_id'] ?? '')) !== '';
	}

	public function delete(string $ownerGroup, string $ownerName, string $mode): void {
		[$ownerGroup, $ownerName, $mode] = $this->normalizeOwner($ownerGroup, $ownerName, $mode);
		$this->eventManager->fire(new ManagedFileStorageDeletingEvent(
			$ownerGroup,
			$ownerName,
			$mode
		));

		$key = $this->settingsName($ownerGroup, $ownerName, $mode);
		$settings = $this->settingsStore->get(self::SETTINGS_GROUP, $key, []);
		$storageId = trim((string)($settings['storage_id'] ?? ''));

		if ($storageId !== '') {
			$this->removeStorage($storageId, $mode);
		}

		if ($this->settingsStore->has(self::SETTINGS_GROUP, $key)) {
			$this->settingsStore->remove(self::SETTINGS_GROUP, $key);
			$this->settingsStore->save();
		}
	}

	public function copyOwner(
		string $sourceGroup,
		string $sourceName,
		string $targetGroup,
		string $targetName,
		string $mode
	): void {
		if ($mode !== Base3IliasFileStorage::MODE_COLLECTION) {
			throw new InvalidArgumentException('Managed owner copy is currently defined for collections only.');
		}
		if (!$this->has($sourceGroup, $sourceName, $mode)) {
			return;
		}
		if ($this->has($targetGroup, $targetName, $mode)) {
			throw new RuntimeException('Target managed file storage already exists.');
		}

		$source = $this->openOrCreate($sourceGroup, $sourceName, $mode);
		$target = $this->openOrCreate($targetGroup, $targetName, $mode);

		try {
			foreach ($source->list('') as $entry) {
				if (!is_array($entry) || (string)($entry['type'] ?? '') !== 'file') {
					continue;
				}

				$name = trim((string)($entry['name'] ?? ''));
				if ($name === '') {
					continue;
				}

				if (!$target->write($name, $source->read($name))) {
					throw new RuntimeException('Managed file storage copy failed for file: ' . $name);
				}
			}
		}
		catch (Throwable $error) {
			$this->delete($targetGroup, $targetName, $mode);
			throw $error;
		}
	}

	public function listDescriptors(string $ownerGroup, string $ownerName, string $mode, string $path = ''): array {
		$path = self::normalizePath($path);
		$storage = $this->openOrCreate($ownerGroup, $ownerName, $mode);
		$items = [];

		foreach ($storage->list($path) as $item) {
			if (!is_array($item) || !isset($item['name'], $item['type'])) {
				continue;
			}

			$name = (string)$item['name'];
			$itemPath = self::joinPath($path, $name);
			$items[] = $this->descriptorFromStorageData($itemPath, $name, $item);
		}

		return $items;
	}

	public function statDescriptor(string $ownerGroup, string $ownerName, string $mode, string $path): ?array {
		$path = self::normalizePath($path);
		$stat = $this->openOrCreate($ownerGroup, $ownerName, $mode)->stat($path);

		if ($stat === null) {
			return null;
		}

		$name = $path === '' ? '/' : basename($path);
		return $this->descriptorFromStorageData($path, $name, $stat);
	}

	public static function normalizePath(string $path): string {
		$path = str_replace('\\', '/', trim($path));
		$path = ltrim($path, '/');

		if ($path === '' || $path === '.') {
			return '';
		}

		$segments = [];
		foreach (explode('/', $path) as $segment) {
			if ($segment === '' || $segment === '.') {
				continue;
			}
			if ($segment === '..') {
				throw new InvalidArgumentException('Parent path segments are not allowed.');
			}
			if (str_contains($segment, "\0")) {
				throw new InvalidArgumentException('Path contains an invalid null byte.');
			}
			$segments[] = $segment;
		}

		return implode('/', $segments);
	}

	public static function joinPath(string $path, string $name): string {
		$path = self::normalizePath($path);
		$name = self::normalizePath($name);

		if ($name === '') {
			return $path;
		}

		return $path === '' ? $name : $path . '/' . $name;
	}

	private function normalizeOwner(string $ownerGroup, string $ownerName, string $mode): array {
		$ownerGroup = trim($ownerGroup);
		$ownerName = trim($ownerName);
		$mode = trim($mode);

		if ($ownerGroup === '' || $ownerName === '') {
			throw new InvalidArgumentException('Managed file storage owner group and name are required.');
		}

		if (!in_array($mode, [Base3IliasFileStorage::MODE_COLLECTION, Base3IliasFileStorage::MODE_CONTAINER], true)) {
			throw new InvalidArgumentException('Unsupported managed file storage mode: ' . $mode);
		}

		return [$ownerGroup, $ownerName, $mode];
	}

	private function settingsName(string $ownerGroup, string $ownerName, string $mode): string {
		return hash('sha256', $ownerGroup . "\0" . $ownerName . "\0" . $mode);
	}

	private function createStorage(string $mode): string {
		return match ($mode) {
			Base3IliasFileStorage::MODE_COLLECTION => $this->createCollection(),
			Base3IliasFileStorage::MODE_CONTAINER => $this->createContainer(),
			default => throw new InvalidArgumentException('Unsupported managed file storage mode: ' . $mode),
		};
	}

	private function removeStorage(string $storageId, string $mode): void {
		match ($mode) {
			Base3IliasFileStorage::MODE_COLLECTION => $this->removeCollection($storageId),
			Base3IliasFileStorage::MODE_CONTAINER => $this->removeContainer($storageId),
			default => throw new InvalidArgumentException('Unsupported managed file storage mode: ' . $mode),
		};
	}

	private function createCollection(): string {
		$collections = $this->iliasContainer->resourceStorage()->collection();
		$identification = $collections->id();
		$collection = $collections->get($identification);
		$collections->store($collection);

		return $identification->serialize();
	}

	private function removeCollection(string $storageId): void {
		$collections = $this->iliasContainer->resourceStorage()->collection();
		if (!$collections->exists($storageId)) {
			return;
		}

		$identification = $collections->id($storageId);
		$collections->remove($identification, new Base3IliasFileStorageStakeholder(), true);

		if ($collections->exists($storageId)) {
			throw new RuntimeException('ILIAS resource collection could not be removed: ' . $storageId);
		}
	}

	private function createContainer(): string {
		$tempPath = rtrim(sys_get_temp_dir(), '/\\')
			. DIRECTORY_SEPARATOR
			. 'base3_filemanager_'
			. bin2hex(random_bytes(12))
			. '.zip';
		$handle = null;

		try {
			if (file_put_contents($tempPath, "PK\x05\x06" . str_repeat("\x00", 18)) === false) {
				throw new RuntimeException('Could not create temporary empty ZIP container.');
			}

			$handle = fopen($tempPath, 'rb');
			if (!is_resource($handle)) {
				throw new RuntimeException('Could not open temporary ZIP container.');
			}

			$identification = $this->iliasContainer
				->resourceStorage()
				->manageContainer()
				->containerFromStream(
					Streams::ofResource($handle),
					new Base3IliasFileStorageStakeholder()
				);

			return $identification->serialize();
		}
		finally {
			if (is_resource($handle)) {
				fclose($handle);
			}
			if (is_file($tempPath)) {
				unlink($tempPath);
			}
		}
	}

	private function removeContainer(string $storageId): void {
		$manager = $this->iliasContainer->resourceStorage()->manageContainer();
		$identification = $manager->find($storageId);

		if ($identification === null) {
			return;
		}

		$manager->remove($identification, new Base3IliasFileStorageStakeholder());

		if ($manager->find($storageId) !== null) {
			throw new RuntimeException('ILIAS resource container could not be removed: ' . $storageId);
		}
	}

	private function descriptorFromStorageData(string $path, string $name, array $data): array {
		$type = (string)($data['type'] ?? '');
		if (!in_array($type, ['file', 'dir'], true)) {
			throw new RuntimeException('Unsupported file storage entry type: ' . $type);
		}

		return [
			'id' => $path === '' ? '/' : $path,
			'name' => $name,
			'path' => $path,
			'kind' => $type === 'dir' ? 'directory' : 'file',
			'size' => $type === 'file' ? (int)($data['size'] ?? 0) : null,
			'mimeType' => (string)($data['mime_type'] ?? ''),
			'modifiedAt' => (string)($data['modified'] ?? ''),
		];
	}
}
