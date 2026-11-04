<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasFileUploadService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class Base3IliasFileUploadInternalsTest extends TestCase {

	public function testExactChunkSizeForFullAndFinalPartialChunks(): void {
		$meta = ['size' => 25, 'chunk_size' => 10];
		self::assertSame(10, $this->invoke('expectedChunkSize', $meta, 0));
		self::assertSame(10, $this->invoke('expectedChunkSize', $meta, 1));
		self::assertSame(5, $this->invoke('expectedChunkSize', $meta, 2));
		self::assertSame(0, $this->invoke('expectedChunkSize', $meta, 3));
	}

	public function testZeroByteUploadExpectsZeroLengthChunk(): void {
		self::assertSame(0, $this->invoke('expectedChunkSize', ['size' => 0, 'chunk_size' => 10], 0));
	}

	public function testFileNamesAreTrimmedButNotRewritten(): void {
		self::assertSame('report.pdf', $this->invoke('normalizeFileName', ' report.pdf '));
		self::assertSame('.config', $this->invoke('normalizeFileName', '.config'));
		self::assertSame('test..pdf', $this->invoke('normalizeFileName', 'test..pdf'));
	}

	public function testInvalidFileNamesAreRejectedBeforeAnyStorageAccess(): void {
		foreach (['', '   ', '.', '..', 'folder/document.pdf', 'folder\\document.pdf', "bad\0.pdf"] as $name) {
			try {
				$this->invoke('normalizeFileName', $name);
				self::fail('Invalid upload file name was accepted.');
			} catch (InvalidArgumentException $error) {
				self::assertSame('Invalid upload file name.', $error->getMessage());
			}
		}
	}

	public function testUploadIdentifierRequiresExactlyThirtyTwoLowercaseHexDigits(): void {
		$this->invoke('assertUploadId', str_repeat('a', 32));
		$this->invoke('assertUploadId', str_repeat('09', 16));
		foreach (['', str_repeat('a', 31), str_repeat('a', 33), str_repeat('A', 32), str_repeat('g', 32), '../' . str_repeat('a', 32)] as $id) {
			try {
				$this->invoke('assertUploadId', $id);
				self::fail('Invalid upload identifier was accepted.');
			} catch (InvalidArgumentException $error) {
				self::assertSame('Invalid upload session id.', $error->getMessage());
			}
		}
	}

	public function testContextValidationRejectsUntrustedOwnerUserAndLimit(): void {
		$this->invoke('assertContext', 'group', 'name', 1, 1);
		foreach ([['', 'name', 1, 1], ['group', '', 1, 1], ['group', 'name', -1, 1], ['group', 'name', 1, 0]] as $args) {
			try {
				$this->invoke('assertContext', ...$args);
				self::fail('Invalid upload context was accepted.');
			} catch (InvalidArgumentException $error) {
				self::assertNotSame('', $error->getMessage());
			}
		}
	}

	public function testEmptyAbortIsIdempotentWithoutFilesystemAccess(): void {
		$service = (new ReflectionClass(Base3IliasFileUploadService::class))->newInstanceWithoutConstructor();
		self::assertSame(['uploadId' => '', 'aborted' => true], $service->abort('module', 'item', 'collection', 1, ''));
	}

	private function invoke(string $method, mixed ...$args): mixed {
		$service = (new ReflectionClass(Base3IliasFileUploadService::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(Base3IliasFileUploadService::class, $method))->invoke($service, ...$args);
	}
}
