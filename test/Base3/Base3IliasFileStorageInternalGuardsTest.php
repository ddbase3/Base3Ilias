<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasFileStorage;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class Base3IliasFileStorageInternalGuardsTest extends TestCase {

	public function testAllPublicFileOperationsRejectParentTraversalBeforeNativeStorageAccess(): void {
		$storage = $this->storage();
		$operations = [
			['list', ['docs/../secret']],
			['read', ['docs/../secret']],
			['write', ['docs/../secret', 'content']],
			['copy', ['docs/../secret', 'target']],
			['copy', ['source', 'docs/../secret']],
			['move', ['docs/../secret', 'target']],
			['move', ['source', 'docs/../secret']],
			['delete', ['docs/../secret']],
			['mkdir', ['docs/../secret']],
			['rmdir', ['docs/../secret']],
			['exists', ['docs/../secret']],
			['stat', ['docs/../secret']]
		];

		foreach ($operations as [$method, $arguments]) {
			try {
				$storage->$method(...$arguments);
				self::fail('Traversal was accepted by ' . $method);
			} catch (InvalidArgumentException $error) {
				self::assertStringContainsString('Parent path segments', $error->getMessage());
			}
		}
	}

	public function testResourceAccessWithoutIdentificationIsRejected(): void {
		try {
			$this->invoke('getResourceIdentification');
			self::fail('Missing resource identification was accepted.');
		} catch (LogicException $error) {
			self::assertStringContainsString('resource identification', $error->getMessage());
		}
	}

	public function testCollectionAccessWithoutIdentificationIsRejected(): void {
		try {
			$this->invoke('getCollectionIdentification');
			self::fail('Missing collection identification was accepted.');
		} catch (LogicException $error) {
			self::assertStringContainsString('collection identification', $error->getMessage());
		}
	}

	public function testFlatNameGuardDoesNotReinterpretValidSingleFileNames(): void {
		self::assertSame('archive.tar.gz', $this->invoke('normalizeFlatFilePath', 'archive.tar.gz'));
		self::assertSame('.htaccess', $this->invoke('normalizeFlatFilePath', '.htaccess'));
	}

	private function storage(): Base3IliasFileStorage {
		return (new ReflectionClass(Base3IliasFileStorage::class))->newInstanceWithoutConstructor();
	}

	private function invoke(string $name, mixed ...$args): mixed {
		return (new ReflectionMethod(Base3IliasFileStorage::class, $name))->invoke($this->storage(), ...$args);
	}
}
