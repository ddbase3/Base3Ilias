<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasFileStorage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class Base3IliasFileStorageModeContractTest extends TestCase {

	public function testInvalidModeAndMissingIdentifierAreRejectedBeforeNativeServiceCalls(): void {
		$storage = $this->storage('unsupported');
		$this->assertInvalidInitialization($storage, 'Unsupported ILIAS file storage mode: unsupported');

		$storage = $this->storage(Base3IliasFileStorage::MODE_COLLECTION);
		(new ReflectionProperty(Base3IliasFileStorage::class, 'rid'))->setValue($storage, '');
		$this->assertInvalidInitialization($storage, 'ILIAS file storage identification must not be empty.');
	}

	public function testFlatModesRejectNestedFilePathsAcrossAllFileOperations(): void {
		foreach ([Base3IliasFileStorage::MODE_SINGLE_FILE, Base3IliasFileStorage::MODE_COLLECTION] as $mode) {
			$storage = $this->storage($mode);
			foreach (['read', 'write', 'copy', 'move', 'delete', 'stat'] as $operation) {
				$arguments = match ($operation) {
					'write' => ['nested/file.txt', 'contents'],
					'copy', 'move' => ['nested/file.txt', 'target.txt'],
					default => ['nested/file.txt'],
				};
				try {
					$storage->$operation(...$arguments);
					self::fail('Nested path was accepted by ' . $mode . ' ' . $operation);
				} catch (InvalidArgumentException $error) {
					self::assertStringContainsString('root directory', $error->getMessage());
				}
			}
		}
	}

	public function testFlatModesRejectTraversalInDirectoryListingBeforeAccessingNativeStorage(): void {
		foreach ([Base3IliasFileStorage::MODE_SINGLE_FILE, Base3IliasFileStorage::MODE_COLLECTION] as $mode) {
			$storage = $this->storage($mode);
			self::assertSame([], $storage->list('subdirectory'));
			try {
				$storage->list('../untrusted');
				self::fail('Traversal was accepted by ' . $mode);
			} catch (InvalidArgumentException $error) {
				self::assertStringContainsString('Parent path segments', $error->getMessage());
			}
		}
	}

	public function testDirectoryChangesAreUnsupportedForFlatStorageModes(): void {
		foreach ([Base3IliasFileStorage::MODE_SINGLE_FILE, Base3IliasFileStorage::MODE_COLLECTION] as $mode) {
			$storage = $this->storage($mode);
			self::assertFalse($storage->mkdir('documents'));
			self::assertFalse($storage->rmdir('documents'));
			self::assertFalse($storage->mkdir(''));
			self::assertFalse($storage->rmdir(''));
		}
	}

	public function testContainerRejectsEmptyFilePathsWithoutCallingResourceStorage(): void {
		$storage = $this->storage(Base3IliasFileStorage::MODE_CONTAINER);
		self::assertSame('', $storage->read(''));
		self::assertFalse($storage->write('', 'content'));
		self::assertFalse($storage->copy('', 'target.txt'));
		self::assertFalse($storage->copy('source.txt', ''));
		self::assertFalse($storage->move('', 'target.txt'));
		self::assertFalse($storage->move('source.txt', ''));
		self::assertFalse($storage->delete(''));
		self::assertFalse($storage->mkdir(''));
		self::assertFalse($storage->rmdir(''));
	}

	public function testContainerRootAndDeletionGuardsHaveDeterministicMetadata(): void {
		$storage = $this->storage(Base3IliasFileStorage::MODE_CONTAINER);
		self::assertSame(['type' => 'dir', 'size' => 0, 'modified' => null],
			(new ReflectionMethod(Base3IliasFileStorage::class, 'statContainer'))->invoke($storage, ''));
		self::assertFalse((new ReflectionMethod(Base3IliasFileStorage::class, 'canDeleteContainerFile'))->invoke($storage, ''));
	}

	public function testAllModesRejectTraversalBeforePerformingWrites(): void {
		foreach ([Base3IliasFileStorage::MODE_SINGLE_FILE, Base3IliasFileStorage::MODE_COLLECTION, Base3IliasFileStorage::MODE_CONTAINER] as $mode) {
			$storage = $this->storage($mode);
			try {
				$storage->write('folder/../protected.txt', 'secret');
				self::fail('Traversal accepted for ' . $mode);
			} catch (InvalidArgumentException $error) {
				self::assertStringContainsString('Parent path segments', $error->getMessage());
			}
		}
	}

	private function assertInvalidInitialization(Base3IliasFileStorage $storage, string $message): void {
		try {
			(new ReflectionMethod(Base3IliasFileStorage::class, 'initializeIdentification'))->invoke($storage);
			self::fail('Expected invalid storage initialization.');
		} catch (InvalidArgumentException $error) {
			self::assertSame($message, $error->getMessage());
		}
	}

	private function storage(string $mode): Base3IliasFileStorage {
		$storage = (new ReflectionClass(Base3IliasFileStorage::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Base3IliasFileStorage::class, 'mode'))->setValue($storage, $mode);
		return $storage;
	}
}
