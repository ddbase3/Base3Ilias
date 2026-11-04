<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Settings\Api\ISettingsStore;
use Base3Ilias\Base3\Base3IliasFileStorage;
use Base3Ilias\Base3\Base3IliasManagedFileStorageService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use ResourceFoundation\Api\IFileStorage;
use ResourceFoundation\Api\IFileStorageFactory;
use RuntimeException;

final class Base3IliasManagedFileStorageDelegationTest extends TestCase {

	public function testExistingStorageIdIsReusedWithoutCreatingNativeResources(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['storage_id' => ' collection-17 ']);
		$store->expects(self::never())->method('set');
		$store->expects(self::never())->method('save');
		$service = $this->service($store);
		self::assertSame('collection-17', $service->getOrCreateStorageIdentifier('ui', 'default', Base3IliasFileStorage::MODE_COLLECTION));
	}

	public function testExistingStorageIdIsDelegatedToTheExistingFactory(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['storage_id' => 'collection-17']);
		$storage = $this->createMock(IFileStorage::class);
		$factory = $this->createMock(IFileStorageFactory::class);
		$factory->expects(self::once())->method('openStorage')
			->with('collection-17', Base3IliasFileStorage::MODE_COLLECTION)->willReturn($storage);
		$service = $this->service($store, $factory);
		self::assertSame($storage, $service->openOrCreate('ui', 'default', Base3IliasFileStorage::MODE_COLLECTION));
	}

	public function testAdoptStoragePersistsNormalizedOwnerOnlyOnce(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('has')->willReturn(false);
		$store->expects(self::once())->method('set')->with(
			'base3ilias-filemanager', hash('sha256', "ui\0default\0collection"), [
				'owner_group' => 'ui', 'owner_name' => 'default',
				'mode' => 'collection', 'storage_id' => 'collection-17'
			]
		);
		$store->expects(self::once())->method('save');
		$this->service($store)->adoptStorageIdentifier(' ui ', ' default ', Base3IliasFileStorage::MODE_COLLECTION, ' collection-17 ');
	}

	public function testAdoptStorageRejectsDuplicateOwnerBeforeWriting(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('has')->willReturn(true);
		$store->expects(self::never())->method('set');
		$store->expects(self::never())->method('save');
		$this->expectException(RuntimeException::class);
		$this->service($store)->adoptStorageIdentifier('ui', 'default', 'collection', 'collection-17');
	}

	public function testHasDependsOnNonblankPersistedStorageId(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturnOnConsecutiveCalls(
			[], ['storage_id' => '  '], ['storage_id' => 'collection-17']
		);
		$service = $this->service($store);
		self::assertFalse($service->has('ui', 'default', 'collection'));
		self::assertFalse($service->has('ui', 'default', 'collection'));
		self::assertTrue($service->has('ui', 'default', 'collection'));
	}

	public function testListDescriptorsFiltersIncompleteEntriesAndFormatsFiles(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['storage_id' => 'collection-17']);
		$storage = $this->createMock(IFileStorage::class);
		$storage->expects(self::once())->method('list')->with('docs')->willReturn([
			['name' => 'document.pdf', 'type' => 'file', 'size' => 15, 'mime_type' => 'application/pdf'],
			['name' => 'subdirectory', 'type' => 'dir'],
			['name' => 'incomplete'],
			'not-an-entry'
		]);
		$factory = $this->createMock(IFileStorageFactory::class);
		$factory->method('openStorage')->willReturn($storage);
		$items = $this->service($store, $factory)->listDescriptors('ui', 'default', 'collection', '/docs/');
		self::assertCount(2, $items);
		self::assertSame('docs/document.pdf', $items[0]['id']);
		self::assertSame('file', $items[0]['kind']);
		self::assertSame(15, $items[0]['size']);
		self::assertSame('docs/subdirectory', $items[1]['path']);
		self::assertSame('directory', $items[1]['kind']);
		self::assertSame(null, $items[1]['size']);
	}

	public function testStatDescriptorReturnsNullForMissingResource(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['storage_id' => 'collection-17']);
		$storage = $this->createMock(IFileStorage::class);
		$storage->expects(self::once())->method('stat')->with('docs/missing.pdf')->willReturn(null);
		$factory = $this->createMock(IFileStorageFactory::class);
		$factory->method('openStorage')->willReturn($storage);
		self::assertSame(null, $this->service($store, $factory)->statDescriptor('ui', 'default', 'collection', '/docs/missing.pdf'));
	}

	public function testStatDescriptorFormatsRootAsDirectory(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['storage_id' => 'collection-17']);
		$storage = $this->createMock(IFileStorage::class);
		$storage->expects(self::once())->method('stat')->with('')->willReturn(['type' => 'dir', 'size' => 0]);
		$factory = $this->createMock(IFileStorageFactory::class);
		$factory->method('openStorage')->willReturn($storage);
		$descriptor = $this->service($store, $factory)->statDescriptor('ui', 'default', 'collection', '/');
		self::assertSame('/', $descriptor['id']);
		self::assertSame('directory', $descriptor['kind']);
	}

	private function service(ISettingsStore $store, ?IFileStorageFactory $factory = null): Base3IliasManagedFileStorageService {
		$service = (new ReflectionClass(Base3IliasManagedFileStorageService::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Base3IliasManagedFileStorageService::class, 'settingsStore'))->setValue($service, $store);
		if ($factory !== null) {
			(new ReflectionProperty(Base3IliasManagedFileStorageService::class, 'fileStorageFactory'))->setValue($service, $factory);
		}
		return $service;
	}
}
