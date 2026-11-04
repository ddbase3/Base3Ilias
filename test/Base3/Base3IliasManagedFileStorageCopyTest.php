<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Event\Api\IEventManager;
use Base3\Settings\Api\ISettingsStore;
use Base3Ilias\Base3\Base3IliasFileStorage;
use Base3Ilias\Base3\Base3IliasManagedFileStorageService;
use ILIAS\DI\Container;
use ILIAS\ResourceStorage\Collection\Collections;
use ILIAS\ResourceStorage\Collection\ResourceCollection;
use ILIAS\ResourceStorage\Identification\ResourceCollectionIdentification;
use ILIAS\ResourceStorage\Services;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IFileStorage;
use ResourceFoundation\Api\IFileStorageFactory;
use RuntimeException;

final class Base3IliasManagedFileStorageCopyTest extends TestCase {

	public function testCopyRejectsContainerModeBeforeAccessingSettingsOrStorage(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects(self::never())->method('get');
		$factory = $this->createMock(IFileStorageFactory::class);
		$factory->expects(self::never())->method('openStorage');
		$service = $this->service($settings, $factory);

		$this->expectException(InvalidArgumentException::class);
		$service->copyOwner('from', 'one', 'to', 'two', Base3IliasFileStorage::MODE_CONTAINER);
	}

	public function testMissingSourceDoesNotCreateAnEmptyTarget(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->method('get')->willReturn([]);
		$settings->expects(self::never())->method('set');
		$factory = $this->createMock(IFileStorageFactory::class);
		$factory->expects(self::never())->method('openStorage');

		$this->service($settings, $factory)->copyOwner('from', 'one', 'to', 'two', 'collection');
	}

	public function testExistingTargetIsNeverOverwritten(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->method('get')->willReturnCallback(static function(string $group, string $key): array {
			return ['storage_id' => $key === hash('sha256', "from\0one\0collection") ? 'source-id' : 'target-id'];
		});
		$settings->expects(self::never())->method('set');
		$factory = $this->createMock(IFileStorageFactory::class);
		$factory->expects(self::never())->method('openStorage');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('already exists');
		$this->service($settings, $factory)->copyOwner('from', 'one', 'to', 'two', 'collection');
	}

	public function testCopyCreatesTargetThroughExistingIrssOwnerAndCopiesOnlyFiles(): void {
		$sourceKey = hash('sha256', "from\0one\0collection");
		$targetKey = hash('sha256', "to\0two\0collection");
		$registered = false;
		$settings = $this->createMock(ISettingsStore::class);
		$settings->method('get')->willReturnCallback(static function(string $group, string $key) use (&$registered, $sourceKey, $targetKey): array {
			if ($key === $sourceKey) return ['storage_id' => 'source-id'];
			if ($key === $targetKey && $registered) return ['storage_id' => 'target-id'];
			return [];
		});
		$settings->expects(self::once())->method('set')->with('base3ilias-filemanager', $targetKey, [
			'owner_group' => 'to', 'owner_name' => 'two', 'mode' => 'collection', 'storage_id' => 'target-id'
		])->willReturnCallback(static function() use (&$registered): void { $registered = true; });
		$settings->expects(self::once())->method('save');

		$source = $this->createMock(IFileStorage::class);
		$source->expects(self::once())->method('list')->with('')->willReturn([
			['type' => 'dir', 'name' => 'ignored'],
			['type' => 'file', 'name' => ' first.txt '],
			['type' => 'file', 'name' => ''],
			['type' => 'file', 'name' => 'second.txt'],
			['name' => 'not-a-file']
		]);
		$source->expects(self::exactly(2))->method('read')->willReturnCallback(
			static fn(string $path): string => match ($path) {
				'first.txt' => 'first', 'second.txt' => 'second',
				default => throw new \RuntimeException('Unexpected source path: ' . $path)
			}
		);
		$target = $this->createMock(IFileStorage::class);
		$copied = [];
		$target->expects(self::exactly(2))->method('write')->willReturnCallback(
			static function(string $name, string $contents) use (&$copied): bool {
				$copied[$name] = $contents;
				return true;
			}
		);
		$factory = $this->createMock(IFileStorageFactory::class);
		$factory->expects(self::exactly(2))->method('openStorage')->willReturnCallback(
			static fn(string $id, string $mode): IFileStorage => match ($id) {
				'source-id' => $source, 'target-id' => $target,
				default => throw new \RuntimeException('Unexpected storage: ' . $id)
			}
		);

		$identification = new ResourceCollectionIdentification('target-id');
		$collection = $this->createMock(ResourceCollection::class);
		$collections = $this->createMock(Collections::class);
		$collections->expects(self::once())->method('id')->willReturn($identification);
		$collections->expects(self::once())->method('get')->with($identification)->willReturn($collection);
		$collections->expects(self::once())->method('store')->with($collection);
		$irss = $this->createMock(Services::class);
		$irss->method('collection')->willReturn($collections);
		$container = $this->createMock(Container::class);
		$container->method('resourceStorage')->willReturn($irss);

		$this->service($settings, $factory, $container)->copyOwner('from', 'one', 'to', 'two', 'collection');
		self::assertSame(['first.txt' => 'first', 'second.txt' => 'second'], $copied);
		self::assertTrue($registered);
	}

	private function service(ISettingsStore $settings, IFileStorageFactory $factory, ?Container $container = null): Base3IliasManagedFileStorageService {
		return new Base3IliasManagedFileStorageService(
			$settings,
			$factory,
			$container ?? $this->createMock(Container::class),
			$this->createMock(IEventManager::class)
		);
	}
}
