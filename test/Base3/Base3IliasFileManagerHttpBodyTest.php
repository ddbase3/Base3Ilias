<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Settings\Api\ISettingsStore;
use Base3Ilias\Base3\Base3IliasFileManagerHttpService;
use Base3Ilias\Base3\Base3IliasFileUploadService;
use Base3Ilias\Base3\Base3IliasManagedFileStorageService;
use ILIAS\DI\Container;
use ILIAS\HTTP\Services;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ResourceFoundation\Api\IFileStorage;
use ResourceFoundation\Api\IFileStorageFactory;

final class Base3IliasFileManagerHttpBodyTest extends TestCase {

	public function testMissingPostBodyIsRepresentedByAnEmptyArray(): void {
		$service = $this->service(null);
		self::assertSame([], (new ReflectionMethod(Base3IliasFileManagerHttpService::class, 'parsedPostBody'))->invoke($service));
	}

	public function testArrayPostBodyPreservesTypedValues(): void {
		$body = ['uploadId' => 'a', 'index' => 3, 'metadata' => ['safe' => true]];
		$service = $this->service($body);
		self::assertSame($body, (new ReflectionMethod(Base3IliasFileManagerHttpService::class, 'parsedPostBody'))->invoke($service));
	}

	public function testUnexpectedPostBodyTypeIsRejectedRatherThanCoerced(): void {
		$service = $this->service((object)['index' => 4]);
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('POST request body must be an array.');
		(new ReflectionMethod(Base3IliasFileManagerHttpService::class, 'parsedPostBody'))->invoke($service);
	}

	public function testDownloadWithoutPathReturnsNotFoundWithoutOpeningStorage(): void {
		$originalStatus = http_response_code();
		$service = $this->service(null, []);
		try {
			$service->handle('download', 'group', 'name', 'collection', 15, 1024);
			self::assertSame(404, http_response_code());
		} finally {
			http_response_code(is_int($originalStatus) ? $originalStatus : 200);
		}
	}

	public function testDownloadOfDirectoryIsNotServedAsFile(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['storage_id' => 'existing-rcid']);
		$storage = $this->createMock(IFileStorage::class);
		$storage->expects(self::once())->method('stat')->with('docs')->willReturn(['type' => 'dir']);
		$storage->expects(self::never())->method('read');
		$factory = $this->createMock(IFileStorageFactory::class);
		$factory->expects(self::once())->method('openStorage')->with('existing-rcid', 'collection')->willReturn($storage);

		$managed = (new ReflectionClass(Base3IliasManagedFileStorageService::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Base3IliasManagedFileStorageService::class, 'settingsStore'))->setValue($managed, $store);
		(new ReflectionProperty(Base3IliasManagedFileStorageService::class, 'fileStorageFactory'))->setValue($managed, $factory);
		$service = $this->service(null, ['path' => '/docs/'], $managed);

		$originalStatus = http_response_code();
		try {
			$service->handle('download', 'group', 'name', 'collection', 15, 1024);
			self::assertSame(404, http_response_code());
		} finally {
			http_response_code(is_int($originalStatus) ? $originalStatus : 200);
		}
	}

	private function service(mixed $body, array $query = [], ?Base3IliasManagedFileStorageService $managed = null): Base3IliasFileManagerHttpService {
		$request = $this->createMock(ServerRequestInterface::class);
		$request->method('getParsedBody')->willReturn($body);
		$request->method('getQueryParams')->willReturn($query);
		$http = $this->createMock(Services::class);
		$http->method('request')->willReturn($request);
		$container = $this->createMock(Container::class);
		$container->method('http')->willReturn($http);

		$managed ??= (new ReflectionClass(Base3IliasManagedFileStorageService::class))->newInstanceWithoutConstructor();
		$upload = (new ReflectionClass(Base3IliasFileUploadService::class))->newInstanceWithoutConstructor();
		return new Base3IliasFileManagerHttpService($managed, $upload, $container);
	}
}
