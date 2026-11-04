<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasFileUploadService;
use Base3Ilias\Base3\Base3IliasManagedFileStorageService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class Base3IliasFileUploadServiceTest extends TestCase {

	public function testStartRejectsMissingOwnerInvalidActorAndNonpositiveLimit(): void {
		$service = $this->service();
		foreach ([
			[' ', 'name', 1, 100],
			['group', ' ', 1, 100],
			['group', 'name', 0, 100],
			['group', 'name', 1, 0]
		] as [$group, $name, $userId, $limit]) {
			try {
				$service->start($group, $name, 'collection', $userId, $limit, []);
				self::fail('Expected invalid upload context to be rejected.');
			} catch (InvalidArgumentException $error) {
				self::assertNotSame('', $error->getMessage());
			}
		}
	}

	public function testStartRejectsInvalidFileNamesAndSizeOrChunkMetadataBeforeStorageAccess(): void {
		$service = $this->service();
		$valid = ['name' => 'file.pdf', 'size' => 15, 'chunkSize' => 10, 'totalChunks' => 2];
		foreach ([
			['name' => '../escape.pdf'], ['name' => '.'], ['name' => "bad\0.pdf"],
			['size' => -1], ['size' => 101], ['chunkSize' => 0],
			['chunkSize' => Base3IliasFileUploadService::MAX_CHUNK_SIZE + 1],
			['totalChunks' => 1], ['path' => '../escape']
		] as $invalid) {
			try {
				$service->start('owner', 'item', 'collection', 1, 100, array_replace($valid, $invalid));
				self::fail('Expected invalid upload metadata to be rejected.');
			} catch (InvalidArgumentException $error) {
				self::assertNotSame('', $error->getMessage());
			}
		}
	}

	public function testRejectsInvalidResumeAndStatusIdentifiersWithoutCreatingSessionFiles(): void {
		$service = $this->service();
		$valid = ['name' => 'file.pdf', 'size' => 1, 'chunkSize' => 1, 'totalChunks' => 1];
		$this->expectException(InvalidArgumentException::class);
		$service->start('owner', 'item', 'collection', 1, 100, $valid + ['uploadId' => '../bad']);
	}

	public function testStatusRejectsUploadIdWithUnexpectedCharacters(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service()->status('owner', 'item', 'collection', 1, 'not-a-hex-id');
	}

	private function service(): Base3IliasFileUploadService {
		// Rejected uploads never touch storage; the dependency must not initialize ILIAS services.
		$unused = (new ReflectionClass(Base3IliasManagedFileStorageService::class))->newInstanceWithoutConstructor();
		return new Base3IliasFileUploadService($unused);
	}
}
