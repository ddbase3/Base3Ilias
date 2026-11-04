<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasFileStorage;
use Base3Ilias\Base3\Base3IliasManagedFileStorageService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class Base3IliasManagedFileStorageDescriptorsTest extends TestCase {

	public function testFileDescriptorIncludesDeclaredFileMetadata(): void {
		self::assertSame([
			'id' => 'docs/report.pdf',
			'name' => 'report.pdf',
			'path' => 'docs/report.pdf',
			'kind' => 'file',
			'size' => 2048,
			'mimeType' => 'application/pdf',
			'modifiedAt' => '2026-01-01T00:00:00+00:00'
		], $this->invoke('descriptorFromStorageData', 'docs/report.pdf', 'report.pdf', [
			'type' => 'file', 'size' => '2048', 'mime_type' => 'application/pdf',
			'modified' => '2026-01-01T00:00:00+00:00'
		]));
	}

	public function testDirectoryDescriptorHasNoFileSizeAndUsesRootId(): void {
		self::assertSame([
			'id' => '/', 'name' => '/', 'path' => '', 'kind' => 'directory',
			'size' => null, 'mimeType' => '', 'modifiedAt' => ''
		], $this->invoke('descriptorFromStorageData', '', '/', ['type' => 'dir', 'size' => 500]));
	}

	public function testMissingFileMetadataUsesEmptyValues(): void {
		$descriptor = $this->invoke('descriptorFromStorageData', 'missing.txt', 'missing.txt', ['type' => 'file']);
		self::assertSame('file', $descriptor['kind']);
		self::assertSame(0, $descriptor['size']);
		self::assertSame('', $descriptor['mimeType']);
		self::assertSame('', $descriptor['modifiedAt']);
	}

	public function testUnsupportedStorageTypesAreRejected(): void {
		foreach (['', 'link', 'directory'] as $type) {
			try {
				$this->invoke('descriptorFromStorageData', 'entry', 'entry', ['type' => $type]);
				self::fail('Unsupported file storage type was accepted.');
			} catch (RuntimeException $error) {
				self::assertStringContainsString('Unsupported file storage entry type:', $error->getMessage());
			}
		}
	}

	public function testOwnerComponentsAreTrimmedAndSupportedModesAccepted(): void {
		self::assertSame(['module', 'item', Base3IliasFileStorage::MODE_COLLECTION],
			$this->invoke('normalizeOwner', ' module ', ' item ', ' ' . Base3IliasFileStorage::MODE_COLLECTION . ' '));
		self::assertSame(['module', 'item', Base3IliasFileStorage::MODE_CONTAINER],
			$this->invoke('normalizeOwner', 'module', 'item', Base3IliasFileStorage::MODE_CONTAINER));
	}

	public function testBlankOwnerOrUnsupportedModeIsRejected(): void {
		foreach ([['', 'item', 'collection'], ['module', ' ', 'collection'], ['module', 'item', 'single_file']] as $values) {
			try {
				$this->invoke('normalizeOwner', ...$values);
				self::fail('Invalid managed storage owner was accepted.');
			} catch (InvalidArgumentException $error) {
				self::assertNotSame('', $error->getMessage());
			}
		}
	}

	public function testSettingsKeysSeparateOwnerComponentsAndModes(): void {
		$key = $this->invoke('settingsName', 'ab', 'c', 'collection');
		self::assertSame(hash('sha256', "ab\0c\0collection"), $key);
		self::assertNotSame($key, $this->invoke('settingsName', 'a', 'bc', 'collection'));
		self::assertNotSame($key, $this->invoke('settingsName', 'ab', 'c', 'container'));
	}

	public function testJoinPathDoesNotIntroduceLeadingSlashForRootItems(): void {
		self::assertSame('readme.md', Base3IliasManagedFileStorageService::joinPath('/', 'readme.md'));
		self::assertSame('docs/readme.md', Base3IliasManagedFileStorageService::joinPath('/docs/', '/readme.md'));
		self::assertSame('docs', Base3IliasManagedFileStorageService::joinPath('docs', '.'));
	}

	private function invoke(string $method, mixed ...$args): mixed {
		$service = (new ReflectionClass(Base3IliasManagedFileStorageService::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(Base3IliasManagedFileStorageService::class, $method))->invoke($service, ...$args);
	}
}
