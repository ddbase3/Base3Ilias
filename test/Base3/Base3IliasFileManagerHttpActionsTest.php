<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Event\Api\IEventManager;
use Base3\Settings\Api\ISettingsStore;
use Base3Ilias\Base3\Base3IliasFileManagerHttpService;
use Base3Ilias\Base3\Base3IliasFileUploadService;
use Base3Ilias\Base3\Base3IliasManagedFileStorageService;
use ILIAS\DI\Container;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ResourceFoundation\Api\IFileStorage;
use ResourceFoundation\Api\IFileStorageFactory;
use RuntimeException;

final class Base3IliasFileManagerHttpActionsTest extends TestCase {

	public function testEmptyListReturnsExistingStorageIdAndDirectoryItems(): void {
		$storage = $this->createMock(IFileStorage::class);
		$storage->expects(self::once())->method('list')->with('')->willReturn([
			['name' => 'report.txt', 'type' => 'file', 'size' => 2]
		]);
		$service = $this->service($storage);
		$result = $this->invoke($service, 'list');
		self::assertSame('', $result['path']);
		self::assertSame('existing-rcid', $result['containerId']);
		self::assertCount(1, $result['items']);
		self::assertSame('report.txt', $result['items'][0]['name']);
	}

	public function testStatOfRootIsDelegatedToExistingStorage(): void {
		$storage = $this->createMock(IFileStorage::class);
		$storage->expects(self::once())->method('stat')->with('')->willReturn(['type' => 'dir', 'size' => 0]);
		$result = $this->invoke($this->service($storage), 'stat');
		self::assertSame('/', $result['id']);
		self::assertSame('directory', $result['kind']);
	}

	public function testMissingStatSetsNotFoundStatusWithoutWriting(): void {
		$storage = $this->createMock(IFileStorage::class);
		$storage->expects(self::once())->method('stat')->with('')->willReturn(null);
		$previous = http_response_code();
		try {
			$this->expectException(RuntimeException::class);
			$this->expectExceptionMessage('File or directory not found.');
			$this->invoke($this->service($storage), 'stat');
		} finally {
			self::assertSame(404, http_response_code());
			http_response_code(is_int($previous) ? $previous : 200);
		}
	}

	public function testEmptyMutationPathsRejectWritesEvenWhenStorageIsAvailable(): void {
		foreach (['mkdir', 'delete', 'rmdir', 'copy', 'move'] as $method) {
			$storage = $this->createMock(IFileStorage::class);
			$storage->expects(self::never())->method($method);
			$service = $this->service($storage);
			try {
				$this->invoke($service, $method);
				self::fail('Empty path was accepted by ' . $method);
			} catch (RuntimeException $error) {
				self::assertStringContainsString('could not be', $error->getMessage());
			}
		}
	}

	private function invoke(Base3IliasFileManagerHttpService $service, string $method): array {
		return (new ReflectionMethod(Base3IliasFileManagerHttpService::class, $method))->invoke(
			$service, 'ui', 'default', 'collection'
		);
	}

	private function service(IFileStorage $storage): Base3IliasFileManagerHttpService {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->method('get')->willReturn(['storage_id' => 'existing-rcid']);
		$factory = $this->createMock(IFileStorageFactory::class);
		$factory->method('openStorage')->with('existing-rcid', 'collection')->willReturn($storage);
		$container = $this->createMock(Container::class);
		$events = $this->createMock(IEventManager::class);
		$managed = new Base3IliasManagedFileStorageService($settings, $factory, $container, $events);
		$upload = (new ReflectionClass(Base3IliasFileUploadService::class))->newInstanceWithoutConstructor();
		return new Base3IliasFileManagerHttpService($managed, $upload, $container);
	}
}
