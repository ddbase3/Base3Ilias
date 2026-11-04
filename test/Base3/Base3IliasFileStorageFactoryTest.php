<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasFileStorage;
use Base3Ilias\Base3\Base3IliasFileStorageFactory;
use ILIAS\DI\Container;
use ILIAS\ResourceStorage\Collection\Collections;
use ILIAS\ResourceStorage\Identification\ResourceCollectionIdentification;
use ILIAS\ResourceStorage\Services;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class Base3IliasFileStorageFactoryTest extends TestCase {

	public function testFactoryRejectsUnsupportedModeBeforeResourceLookup(): void {
		$container = $this->createMock(Container::class);
		$services = (new ReflectionClass(Services::class))->newInstanceWithoutConstructor();
		$container->expects(self::once())->method('resourceStorage')->willReturn($services);

		$factory = new Base3IliasFileStorageFactory($container);
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported ILIAS file storage mode: unknown');
		$factory->openStorage('existing-rid', 'unknown');
	}

	public function testFactoryRejectsEmptyIdentificationBeforeResourceLookup(): void {
		$container = $this->createMock(Container::class);
		$services = (new ReflectionClass(Services::class))->newInstanceWithoutConstructor();
		$container->expects(self::once())->method('resourceStorage')->willReturn($services);

		$factory = new Base3IliasFileStorageFactory($container);
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('ILIAS file storage identification must not be empty.');
		$factory->openStorage('', Base3IliasFileStorage::MODE_SINGLE_FILE);
	}

	public function testFactoryOpensExistingCollectionWithoutCreatingAnotherStorage(): void {
		$identification = (new ReflectionClass(ResourceCollectionIdentification::class))->newInstanceWithoutConstructor();
		$collections = $this->createMock(Collections::class);
		$collections->expects(self::once())->method('id')->with('existing-rcid')->willReturn($identification);
		$collections->expects(self::once())->method('exists')->with('existing-rcid')->willReturn(true);
		$collections->expects(self::never())->method('store');

		$services = $this->createMock(Services::class);
		$services->expects(self::exactly(2))->method('collection')->willReturn($collections);
		$container = $this->createMock(Container::class);
		$container->expects(self::once())->method('resourceStorage')->willReturn($services);

		$storage = (new Base3IliasFileStorageFactory($container))
			->openStorage('existing-rcid', Base3IliasFileStorage::MODE_COLLECTION);
		self::assertInstanceOf(Base3IliasFileStorage::class, $storage);
		self::assertSame([], $storage->list('not-a-flat-file-path'));
	}
}
