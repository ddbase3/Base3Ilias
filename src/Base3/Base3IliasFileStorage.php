<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use ILIAS\Filesystem\Stream\Streams;
use ILIAS\ResourceStorage\Identification\ResourceCollectionIdentification;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;
use ILIAS\ResourceStorage\Resource\ResourceType;
use ILIAS\ResourceStorage\Revision\Revision;
use ILIAS\ResourceStorage\Services;
use ILIAS\ResourceStorage\Stakeholder\ResourceStakeholder;
use ResourceFoundation\Api\IFileStorage;
use ZipArchive;

final class Base3IliasFileStorage implements IFileStorage {

	public const MODE_SINGLE_FILE = 'single_file';
	public const MODE_COLLECTION = 'collection';
	public const MODE_CONTAINER = 'container';

	private ?ResourceIdentification $resourceIdentification = null;
	private ?ResourceCollectionIdentification $collectionIdentification = null;

	public function __construct(
		private readonly string $rid,
		private readonly string $mode,
		private readonly Services $resourceStorage,
		private readonly ResourceStakeholder $stakeholder
	) {
		$this->initializeIdentification();
	}

	public function list(string $path = ''): array {
		$path = $this->normalizePath($path);

		return match ($this->mode) {
			self::MODE_SINGLE_FILE => $this->listSingleFile($path),
			self::MODE_COLLECTION => $this->listCollection($path),
			self::MODE_CONTAINER => $this->listContainer($path),
		};
	}

	public function read(string $path): string {
		$path = $this->normalizePath($path);

		return match ($this->mode) {
			self::MODE_SINGLE_FILE => $this->readSingleFile($path),
			self::MODE_COLLECTION => $this->readCollection($path),
			self::MODE_CONTAINER => $this->readContainer($path),
		};
	}

	public function write(string $path, string $content): bool {
		$path = $this->normalizePath($path);

		return match ($this->mode) {
			self::MODE_SINGLE_FILE => $this->writeSingleFile($path, $content),
			self::MODE_COLLECTION => $this->writeCollection($path, $content),
			self::MODE_CONTAINER => $this->writeContainer($path, $content),
		};
	}

	public function delete(string $path): bool {
		$path = $this->normalizePath($path);

		return match ($this->mode) {
			self::MODE_SINGLE_FILE => $this->deleteSingleFile($path),
			self::MODE_COLLECTION => $this->deleteCollection($path),
			self::MODE_CONTAINER => $this->deleteContainer($path),
		};
	}

	public function mkdir(string $path): bool {
		$path = $this->normalizePath($path);

		if ($this->mode !== self::MODE_CONTAINER || $path === '') {
			return false;
		}

		$stat = $this->statContainer($path);
		if ($stat !== null) {
			return $stat['type'] === 'dir';
		}

		return $this->resourceStorage->manageContainer()->createDirectoryInsideContainer(
			$this->getResourceIdentification(),
			$path
		);
	}

	public function rmdir(string $path): bool {
		$path = $this->normalizePath($path);

		if ($this->mode !== self::MODE_CONTAINER || $path === '') {
			return false;
		}

		$stat = $this->statContainer($path);
		if ($stat === null || $stat['type'] !== 'dir') {
			return false;
		}

		return $this->resourceStorage->manageContainer()->removePathInsideContainer(
			$this->getResourceIdentification(),
			$path . '/'
		);
	}

	public function exists(string $path): bool {
		$path = $this->normalizePath($path);

		if ($path === '') {
			return $this->storageExists();
		}

		return match ($this->mode) {
			self::MODE_SINGLE_FILE => $this->singleFileName() === $this->normalizeFlatFilePath($path),
			self::MODE_COLLECTION => $this->findCollectionIdentification($this->normalizeFlatFilePath($path)) !== null,
			self::MODE_CONTAINER => $this->statContainer($path) !== null,
		};
	}

	public function stat(string $path): ?array {
		$path = $this->normalizePath($path);

		if ($path === '') {
			return $this->storageExists() ? $this->directoryStat() : null;
		}

		return match ($this->mode) {
			self::MODE_SINGLE_FILE => $this->statSingleFile($path),
			self::MODE_COLLECTION => $this->statCollection($path),
			self::MODE_CONTAINER => $this->statContainer($path),
		};
	}

	private function initializeIdentification(): void {
		if (!in_array($this->mode, [
			self::MODE_SINGLE_FILE,
			self::MODE_COLLECTION,
			self::MODE_CONTAINER,
		], true)) {
			throw new \InvalidArgumentException('Unsupported ILIAS file storage mode: ' . $this->mode);
		}

		if ($this->rid === '') {
			throw new \InvalidArgumentException('ILIAS file storage identification must not be empty.');
		}

		if ($this->mode === self::MODE_COLLECTION) {
			if (!$this->resourceStorage->collection()->exists($this->rid)) {
				throw new \InvalidArgumentException('ILIAS resource collection not found: ' . $this->rid);
			}

			$this->collectionIdentification = $this->resourceStorage->collection()->id($this->rid);
			return;
		}

		$identification = $this->resourceStorage->manage()->find($this->rid);
		if (!$identification instanceof ResourceIdentification) {
			throw new \InvalidArgumentException('ILIAS resource not found: ' . $this->rid);
		}

		$resource = $this->resourceStorage->manage()->getResource($identification);
		$expectedType = $this->mode === self::MODE_CONTAINER
			? ResourceType::CONTAINER
			: ResourceType::SINGLE_FILE;

		if ($resource->getType() !== $expectedType) {
			throw new \InvalidArgumentException(
				'ILIAS resource type does not match file storage mode: ' . $this->mode
			);
		}

		$this->resourceIdentification = $identification;
	}

	private function listSingleFile(string $path): array {
		if ($path !== '') {
			return [];
		}

		$revision = $this->resourceStorage->manage()->getCurrentRevision($this->getResourceIdentification());
		return [
			$this->fileStatFromRevision($revision, true)
		];
	}

	private function readSingleFile(string $path): string {
		$path = $this->normalizeFlatFilePath($path);
		if ($path !== $this->singleFileName()) {
			return '';
		}

		return $this->readResource($this->getResourceIdentification());
	}

	private function writeSingleFile(string $path, string $content): bool {
		$path = $this->normalizeFlatFilePath($path);
		$this->resourceStorage->manage()->replaceWithStream(
			$this->getResourceIdentification(),
			Streams::ofString($content),
			$this->stakeholder,
			$path
		);

		return true;
	}

	private function deleteSingleFile(string $path): bool {
		$path = $this->normalizeFlatFilePath($path);
		if ($path !== $this->singleFileName()) {
			return false;
		}

		$this->resourceStorage->manage()->remove(
			$this->getResourceIdentification(),
			$this->stakeholder
		);

		return $this->resourceStorage->manage()->find($this->rid) === null;
	}

	private function statSingleFile(string $path): ?array {
		$path = $this->normalizeFlatFilePath($path);
		if ($path !== $this->singleFileName()) {
			return null;
		}

		$revision = $this->resourceStorage->manage()->getCurrentRevision($this->getResourceIdentification());
		return $this->fileStatFromRevision($revision, false);
	}

	private function singleFileName(): string {
		return $this->resourceStorage
			->manage()
			->getCurrentRevision($this->getResourceIdentification())
			->getInformation()
			->getTitle();
	}

	private function listCollection(string $path): array {
		if ($path !== '') {
			return [];
		}

		$items = [];
		foreach ($this->collectionEntries() as $name => $entry) {
			$items[] = $this->fileStatFromRevision($entry['revision'], true);
		}

		usort($items, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
		return $items;
	}

	private function readCollection(string $path): string {
		$path = $this->normalizeFlatFilePath($path);
		$identification = $this->findCollectionIdentification($path);
		if (!$identification instanceof ResourceIdentification) {
			return '';
		}

		return $this->readResource($identification);
	}

	private function writeCollection(string $path, string $content): bool {
		$path = $this->normalizeFlatFilePath($path);
		$identification = $this->findCollectionIdentification($path);

		if ($identification instanceof ResourceIdentification) {
			$this->resourceStorage->manage()->replaceWithStream(
				$identification,
				Streams::ofString($content),
				$this->stakeholder,
				$path
			);
			return true;
		}

		$newIdentification = $this->resourceStorage->manage()->stream(
			Streams::ofString($content),
			$this->stakeholder,
			$path
		);

		$collection = $this->resourceStorage->collection()->get($this->getCollectionIdentification());
		$collection->add($newIdentification);
		$this->resourceStorage->collection()->store($collection);

		return true;
	}

	private function deleteCollection(string $path): bool {
		$path = $this->normalizeFlatFilePath($path);
		$identification = $this->findCollectionIdentification($path);
		if (!$identification instanceof ResourceIdentification) {
			return false;
		}

		$collection = $this->resourceStorage->collection()->get($this->getCollectionIdentification());
		$collection->remove($identification);
		$this->resourceStorage->collection()->store($collection);
		$this->resourceStorage->manage()->remove($identification, $this->stakeholder);

		return true;
	}

	private function statCollection(string $path): ?array {
		$path = $this->normalizeFlatFilePath($path);
		$entries = $this->collectionEntries();
		return isset($entries[$path])
			? $this->fileStatFromRevision($entries[$path]['revision'], false)
			: null;
	}

	private function findCollectionIdentification(string $name): ?ResourceIdentification {
		$entries = $this->collectionEntries();
		return $entries[$name]['identification'] ?? null;
	}

	private function collectionEntries(): array {
		$collection = $this->resourceStorage->collection()->get($this->getCollectionIdentification());
		$entries = [];

		foreach ($collection->getResourceIdentifications() as $identification) {
			$resource = $this->resourceStorage->manage()->getResource($identification);
			if ($resource->getType() !== ResourceType::SINGLE_FILE) {
				throw new \RuntimeException(
					'ILIAS resource collection contains a non-file resource: ' . $identification->serialize()
				);
			}

			$revision = $this->resourceStorage->manage()->getCurrentRevision($identification);
			$name = $revision->getInformation()->getTitle();
			if (isset($entries[$name])) {
				throw new \RuntimeException('ILIAS resource collection contains duplicate file name: ' . $name);
			}

			$entries[$name] = [
				'identification' => $identification,
				'revision' => $revision,
			];
		}

		return $entries;
	}

	private function listContainer(string $path): array {
		$entries = $this->containerEntries();
		if ($path !== '' && (!isset($entries[$path]) || $entries[$path]['type'] !== 'dir')) {
			return [];
		}

		$items = [];
		foreach ($entries as $entryPath => $entry) {
			$parent = dirname($entryPath);
			$parent = $parent === '.' ? '' : $parent;
			if ($parent !== $path) {
				continue;
			}

			$items[] = [
				'name' => basename($entryPath),
				'type' => $entry['type'],
				'size' => $entry['size'],
				'modified' => $entry['modified'],
			];
		}

		usort($items, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
		return $items;
	}

	private function readContainer(string $path): string {
		if ($path === '') {
			return '';
		}

		$entries = $this->containerEntries();
		if (!isset($entries[$path]) || $entries[$path]['type'] !== 'file') {
			return '';
		}

		$zip = $this->openContainerArchive();
		try {
			$content = $zip->getFromName($entries[$path]['raw_name']);
			return is_string($content) ? $content : '';
		} finally {
			$zip->close();
		}
	}

	private function writeContainer(string $path, string $content): bool {
		if ($path === '') {
			return false;
		}

		return $this->resourceStorage->manageContainer()->addStreamToContainer(
			$this->getResourceIdentification(),
			Streams::ofString($content),
			$path
		);
	}

	private function deleteContainer(string $path): bool {
		if ($path === '') {
			return false;
		}

		$stat = $this->statContainer($path);
		if ($stat === null || $stat['type'] !== 'file') {
			return false;
		}

		foreach (array_keys($this->containerEntries()) as $entryPath) {
			if ($entryPath !== $path && str_starts_with($entryPath, $path)) {
				return false;
			}
		}

		return $this->resourceStorage->manageContainer()->removePathInsideContainer(
			$this->getResourceIdentification(),
			$path
		);
	}

	private function statContainer(string $path): ?array {
		if ($path === '') {
			return $this->directoryStat();
		}

		$entries = $this->containerEntries();
		if (!isset($entries[$path])) {
			return null;
		}

		return [
			'type' => $entries[$path]['type'],
			'size' => $entries[$path]['size'],
			'modified' => $entries[$path]['modified'],
		];
	}

	private function containerEntries(): array {
		$zip = $this->openContainerArchive();
		$entries = [];

		try {
			for ($index = 0; $index < $zip->numFiles; $index++) {
				$rawName = $zip->getNameIndex($index);
				if (!is_string($rawName) || $rawName === '') {
					continue;
				}

				$isDirectory = str_ends_with($rawName, '/');
				try {
					$path = $this->normalizePath(rtrim($rawName, '/'));
				} catch (\InvalidArgumentException) {
					continue;
				}

				if ($path === '') {
					continue;
				}

				$stat = $zip->statIndex($index);
				if (isset($entries[$path]) && ($entries[$path]['type'] === 'file' || !$isDirectory)) {
					throw new \RuntimeException('ILIAS container contains duplicate logical file path: ' . $path);
				}

				$entries[$path] = [
					'type' => $isDirectory ? 'dir' : 'file',
					'size' => $isDirectory ? 0 : (int)($stat['size'] ?? 0),
					'modified' => isset($stat['mtime']) && (int)$stat['mtime'] > 0
						? date('c', (int)$stat['mtime'])
						: null,
					'raw_name' => $rawName,
				];

				$parent = dirname($path);
				while ($parent !== '.' && $parent !== '') {
					if (!isset($entries[$parent])) {
						$entries[$parent] = [
							'type' => 'dir',
							'size' => 0,
							'modified' => null,
							'raw_name' => $parent . '/',
						];
					}
					$parent = dirname($parent);
				}
			}
		} finally {
			$zip->close();
		}

		return $entries;
	}

	private function openContainerArchive(): ZipArchive {
		$identification = $this->getResourceIdentification();
		$revision = $this->resourceStorage->manage()->getCurrentRevisionIncludingDraft($identification);
		$stream = $this->resourceStorage
			->consume()
			->stream($identification)
			->setRevisionNumber($revision->getVersionNumber())
			->getStream();
		$uri = $stream->getMetadata('uri');
		if (!is_string($uri) || $uri === '') {
			throw new \RuntimeException('ILIAS container stream does not expose a readable URI.');
		}

		$zip = new ZipArchive();
		if ($zip->open($uri) !== true) {
			throw new \RuntimeException('Unable to open ILIAS container resource as ZIP archive.');
		}

		return $zip;
	}

	private function readResource(ResourceIdentification $identification): string {
		$stream = $this->resourceStorage->consume()->stream($identification)->getStream();
		return $stream->getContents();
	}

	private function fileStatFromRevision(Revision $revision, bool $includeName): array {
		$information = $revision->getInformation();
		$stat = [
			'type' => 'file',
			'size' => $information->getSize(),
			'modified' => $information->getCreationDate()->format(DATE_ATOM),
		];

		if ($includeName) {
			$stat = ['name' => $information->getTitle()] + $stat;
		}

		return $stat;
	}

	private function directoryStat(): array {
		return [
			'type' => 'dir',
			'size' => 0,
			'modified' => null,
		];
	}

	private function normalizeFlatFilePath(string $path): string {
		if ($path === '' || str_contains($path, '/')) {
			throw new \InvalidArgumentException('This ILIAS file storage mode only supports files in the root directory.');
		}
		return $path;
	}

	private function normalizePath(string $path): string {
		$path = str_replace('\\', '/', $path);
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
				throw new \InvalidArgumentException('Parent path segments are not allowed in ILIAS file storage paths.');
			}
			$segments[] = $segment;
		}

		return implode('/', $segments);
	}

	private function storageExists(): bool {
		return match ($this->mode) {
			self::MODE_COLLECTION => $this->resourceStorage->collection()->exists($this->rid),
			self::MODE_SINGLE_FILE, self::MODE_CONTAINER => $this->resourceStorage->manage()->find($this->rid) !== null,
		};
	}

	private function getResourceIdentification(): ResourceIdentification {
		if (!$this->resourceIdentification instanceof ResourceIdentification) {
			throw new \LogicException('This ILIAS file storage does not use a resource identification.');
		}
		return $this->resourceIdentification;
	}

	private function getCollectionIdentification(): ResourceCollectionIdentification {
		if (!$this->collectionIdentification instanceof ResourceCollectionIdentification) {
			throw new \LogicException('This ILIAS file storage does not use a collection identification.');
		}
		return $this->collectionIdentification;
	}
}
