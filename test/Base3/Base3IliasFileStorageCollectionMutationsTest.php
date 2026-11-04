<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasFileStorage;
use ILIAS\ResourceStorage\Collection\Collections;
use ILIAS\ResourceStorage\Collection\ResourceCollection;
use ILIAS\ResourceStorage\Identification\ResourceCollectionIdentification;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;
use ILIAS\ResourceStorage\Information\Information;
use ILIAS\ResourceStorage\Manager\Manager;
use ILIAS\ResourceStorage\Resource\ResourceType;
use ILIAS\ResourceStorage\Resource\StorableResource;
use ILIAS\ResourceStorage\Revision\Revision;
use ILIAS\ResourceStorage\Services;
use ILIAS\ResourceStorage\Stakeholder\ResourceStakeholder;
use PHPUnit\Framework\TestCase;

final class Base3IliasFileStorageCollectionMutationsTest extends TestCase {

	public function testWritingNewCollectionMemberStoresExactlyOneNewIdentification(): void {
		$created = new ResourceIdentification('new-rid');
		$collection = $this->createMock(ResourceCollection::class);
		$collection->method('getResourceIdentifications')->willReturn([]);
		$collection->expects(self::once())->method('add')->with($created);
		$manager = $this->createMock(Manager::class);
		$manager->expects(self::once())->method('stream')->willReturn($created);
		$manager->expects(self::never())->method('replaceWithStream');
		$collections = $this->createMock(Collections::class);
		$collections->expects(self::once())->method('store')->with($collection);
		$storage = $this->storage($collection, $manager, $collections);

		self::assertTrue($storage->write('document.txt', 'content'));
	}

	public function testReplacingExistingMemberDoesNotAddDuplicateToCollection(): void {
		$existing = new ResourceIdentification('old-rid');
		$collection = $this->createMock(ResourceCollection::class);
		$collection->method('getResourceIdentifications')->willReturn([$existing]);
		$collection->expects(self::never())->method('add');
		$manager = $this->fileManager($existing, 'document.txt');
		$manager->expects(self::once())->method('replaceWithStream');
		$manager->expects(self::never())->method('stream');
		$collections = $this->createMock(Collections::class);
		$collections->expects(self::never())->method('store');
		$storage = $this->storage($collection, $manager, $collections);

		self::assertTrue($storage->write('document.txt', 'replacement'));
	}

	public function testDeletingExistingMemberUpdatesCollectionAndResourceManager(): void {
		$existing = new ResourceIdentification('delete-rid');
		$collection = $this->createMock(ResourceCollection::class);
		$collection->method('getResourceIdentifications')->willReturn([$existing]);
		$collection->expects(self::once())->method('remove')->with($existing);
		$manager = $this->fileManager($existing, 'document.txt');
		$manager->expects(self::once())->method('remove');
		$collections = $this->createMock(Collections::class);
		$collections->expects(self::once())->method('store')->with($collection);
		$storage = $this->storage($collection, $manager, $collections);

		self::assertTrue($storage->delete('document.txt'));
	}

	public function testSameSourceAndTargetDoesNotCreateNewCollectionMember(): void {
		$existing = new ResourceIdentification('same-rid');
		$collection = $this->createMock(ResourceCollection::class);
		$collection->method('getResourceIdentifications')->willReturn([$existing]);
		$manager = $this->fileManager($existing, 'document.txt');
		$manager->expects(self::never())->method('stream');
		$manager->expects(self::never())->method('remove');
		$collections = $this->createMock(Collections::class);
		$collections->expects(self::never())->method('store');
		$storage = $this->storage($collection, $manager, $collections);

		self::assertTrue($storage->copy('document.txt', 'document.txt'));
		self::assertTrue($storage->move('document.txt', 'document.txt'));
	}

	private function fileManager(ResourceIdentification $id, string $name): Manager {
		$resource = $this->createMock(StorableResource::class);
		$resource->method('getType')->willReturn(ResourceType::SINGLE_FILE);
		$information = $this->createMock(Information::class);
		$information->method('getTitle')->willReturn($name);
		$revision = $this->createMock(Revision::class);
		$revision->method('getInformation')->willReturn($information);
		$manager = $this->createMock(Manager::class);
		$manager->method('getResource')->with($id)->willReturn($resource);
		$manager->method('getCurrentRevision')->with($id)->willReturn($revision);
		return $manager;
	}

	private function storage(ResourceCollection $collection, Manager $manager, Collections $collections): Base3IliasFileStorage {
		$identification = new ResourceCollectionIdentification('collection-id');
		$collections->method('exists')->with('collection-id')->willReturn(true);
		$collections->method('id')->with('collection-id')->willReturn($identification);
		$collections->method('get')->with($identification)->willReturn($collection);
		$services = $this->createMock(Services::class);
		$services->method('collection')->willReturn($collections);
		$services->method('manage')->willReturn($manager);
		return new Base3IliasFileStorage(
			'collection-id', Base3IliasFileStorage::MODE_COLLECTION,
			$services, $this->createMock(ResourceStakeholder::class)
		);
	}
}
