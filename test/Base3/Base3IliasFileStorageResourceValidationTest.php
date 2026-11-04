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
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Base3IliasFileStorageResourceValidationTest extends TestCase {

	public function testOpeningMissingCollectionFailsBeforeAnyMemberIsRead(): void {
		$collections = $this->createMock(Collections::class);
		$collections->expects(self::once())->method('exists')->with('missing-rcid')->willReturn(false);
		$services = $this->createMock(Services::class);
		$services->method('collection')->willReturn($collections);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('ILIAS resource collection not found');
		new Base3IliasFileStorage('missing-rcid', Base3IliasFileStorage::MODE_COLLECTION, $services, $this->stakeholder());
	}

	public function testOpeningMissingSingleFileFailsBeforeReadingItsRevision(): void {
		$manager = $this->createMock(Manager::class);
		$manager->expects(self::once())->method('find')->with('missing-rid')->willReturn(null);
		$services = $this->createMock(Services::class);
		$services->method('manage')->willReturn($manager);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('ILIAS resource not found');
		new Base3IliasFileStorage('missing-rid', Base3IliasFileStorage::MODE_SINGLE_FILE, $services, $this->stakeholder());
	}

	public function testOpeningSingleFileWithContainerResourceIsRejected(): void {
		$identification = new ResourceIdentification('existing-rid');
		$resource = $this->createMock(StorableResource::class);
		$resource->method('getType')->willReturn(ResourceType::CONTAINER);
		$manager = $this->createMock(Manager::class);
		$manager->method('find')->willReturn($identification);
		$manager->method('getResource')->willReturn($resource);
		$services = $this->createMock(Services::class);
		$services->method('manage')->willReturn($manager);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('does not match file storage mode');
		new Base3IliasFileStorage('existing-rid', Base3IliasFileStorage::MODE_SINGLE_FILE, $services, $this->stakeholder());
	}

	public function testEmptyCollectionHasNoFilesAndRejectsMissingSourceWithoutWriting(): void {
		$collection = $this->createMock(ResourceCollection::class);
		$collection->method('getResourceIdentifications')->willReturn([]);
		$storage = $this->collectionStorage($collection);

		self::assertSame([], $storage->list(''));
		self::assertSame([], $storage->list('not-root'));
		self::assertSame('', $storage->read('missing.txt'));
		self::assertFalse($storage->exists('missing.txt'));
		self::assertSame(null, $storage->stat('missing.txt'));
		self::assertFalse($storage->copy('missing.txt', 'other.txt'));
		self::assertFalse($storage->move('missing.txt', 'other.txt'));
		self::assertFalse($storage->move('missing.txt', 'missing.txt'));
		self::assertFalse($storage->delete('missing.txt'));
		self::assertSame(['type' => 'dir', 'size' => 0, 'modified' => null], $storage->stat(''));
	}

	public function testCollectionRejectsNonFileMembersInsteadOfReturningAmbiguousEntries(): void {
		$identification = new ResourceIdentification('container-rid');
		$collection = $this->createMock(ResourceCollection::class);
		$collection->method('getResourceIdentifications')->willReturn([$identification]);
		$resource = $this->createMock(StorableResource::class);
		$resource->method('getType')->willReturn(ResourceType::CONTAINER);
		$manager = $this->createMock(Manager::class);
		$manager->method('getResource')->with($identification)->willReturn($resource);
		$storage = $this->collectionStorage($collection, $manager);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('contains a non-file resource');
		$storage->list('');
	}

	public function testCollectionRejectsDuplicateCurrentFileNames(): void {
		$first = new ResourceIdentification('first-rid');
		$second = new ResourceIdentification('second-rid');
		$collection = $this->createMock(ResourceCollection::class);
		$collection->method('getResourceIdentifications')->willReturn([$first, $second]);
		$resource = $this->createMock(StorableResource::class);
		$resource->method('getType')->willReturn(ResourceType::SINGLE_FILE);
		$information = $this->createMock(Information::class);
		$information->method('getTitle')->willReturn('duplicate.txt');
		$revision = $this->createMock(Revision::class);
		$revision->method('getInformation')->willReturn($information);
		$manager = $this->createMock(Manager::class);
		$manager->method('getResource')->willReturn($resource);
		$manager->method('getCurrentRevision')->willReturn($revision);
		$storage = $this->collectionStorage($collection, $manager);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('contains duplicate file name: duplicate.txt');
		$storage->list('');
	}

	public function testSingleFileExposesRevisionMetadataAndDoesNotCopyToAnotherPath(): void {
		$identification = new ResourceIdentification('one-file-rid');
		$resource = $this->createMock(StorableResource::class);
		$resource->method('getType')->willReturn(ResourceType::SINGLE_FILE);
		$information = $this->createMock(Information::class);
		$information->method('getTitle')->willReturn('report.pdf');
		$information->method('getSize')->willReturn(123);
		$information->method('getCreationDate')->willReturn(new \DateTimeImmutable('2024-05-12T11:00:00+00:00'));
		$revision = $this->createMock(Revision::class);
		$revision->method('getInformation')->willReturn($information);
		$manager = $this->createMock(Manager::class);
		$manager->method('find')->with('one-file-rid')->willReturn($identification);
		$manager->method('getResource')->willReturn($resource);
		$manager->method('getCurrentRevision')->willReturn($revision);
		$services = $this->createMock(Services::class);
		$services->method('manage')->willReturn($manager);
		$storage = new Base3IliasFileStorage('one-file-rid', Base3IliasFileStorage::MODE_SINGLE_FILE, $services, $this->stakeholder());

		self::assertSame([[
			'name' => 'report.pdf', 'type' => 'file', 'size' => 123,
			'modified' => '2024-05-12T11:00:00+00:00'
		]], $storage->list(''));
		self::assertSame([
			'type' => 'file', 'size' => 123, 'modified' => '2024-05-12T11:00:00+00:00'
		], $storage->stat('report.pdf'));
		self::assertSame(null, $storage->stat('missing.pdf'));
		self::assertSame('', $storage->read('missing.pdf'));
		self::assertTrue($storage->exists('report.pdf'));
		self::assertFalse($storage->exists('missing.pdf'));
		self::assertTrue($storage->copy('report.pdf', 'report.pdf'));
		self::assertFalse($storage->copy('report.pdf', 'copy.pdf'));
		self::assertFalse($storage->copy('missing.pdf', 'missing.pdf'));
		self::assertTrue($storage->move('report.pdf', 'report.pdf'));
		self::assertFalse($storage->delete('missing.pdf'));
	}

	private function collectionStorage(ResourceCollection $collection, ?Manager $manager = null): Base3IliasFileStorage {
		$collectionIdentification = new ResourceCollectionIdentification('collection-rcid');
		$collections = $this->createMock(Collections::class);
		$collections->method('exists')->with('collection-rcid')->willReturn(true);
		$collections->method('id')->with('collection-rcid')->willReturn($collectionIdentification);
		$collections->method('get')->with($collectionIdentification)->willReturn($collection);
		$services = $this->createMock(Services::class);
		$services->method('collection')->willReturn($collections);
		if ($manager !== null) $services->method('manage')->willReturn($manager);
		return new Base3IliasFileStorage('collection-rcid', Base3IliasFileStorage::MODE_COLLECTION, $services, $this->stakeholder());
	}

	private function stakeholder(): ResourceStakeholder {
		return $this->createMock(ResourceStakeholder::class);
	}
}
