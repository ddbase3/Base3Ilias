<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Base3Ilias for BASE3 Framework.
 *
 * Base3Ilias integrates BASE3 with the ILIAS learning platform.
 * It provides native identity, services, assets and lifecycle adapters.
 * Application modules use the shared BASE3 Foundation contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0-or-later
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 ***********************************************************************/


namespace Base3\Base3Ilias\Test\Base3;

use Base3\Base3Ilias\Base3\Base3IliasFileStorage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class Base3IliasFileStoragePathValidationTest extends TestCase {

	public function testNormalizesSeparatorsRootAndCurrentDirectorySegments(): void {
		self::assertSame('folder/file.txt', $this->invoke('normalizePath', '/folder//./file.txt'));
		self::assertSame('folder/file.txt', $this->invoke('normalizePath', '\\folder\\file.txt'));
		self::assertSame('', $this->invoke('normalizePath', '/./'));
		self::assertSame('', $this->invoke('normalizePath', ''));
	}

	public function testParentTraversalSegmentsAreRejected(): void {
		foreach (['../secret', 'folder/../secret', 'folder\\..\\secret'] as $path) {
			try {
				$this->invoke('normalizePath', $path);
				self::fail('Expected traversal rejection for ' . $path);
			} catch (InvalidArgumentException $error) {
				self::assertSame('Parent path segments are not allowed in ILIAS file storage paths.', $error->getMessage());
			}
		}
	}

	public function testNonTraversalDotNamesRemainValid(): void {
		self::assertSame('.hidden/..hidden', $this->invoke('normalizePath', '.hidden/..hidden'));
	}

	public function testFlatFileModesRejectDirectoriesAndEmptyNames(): void {
		foreach (['', 'folder/file.txt', 'folder/'] as $path) {
			try {
				$this->invoke('normalizeFlatFilePath', $path);
				self::fail('Expected flat path rejection for ' . $path);
			} catch (InvalidArgumentException $error) {
				self::assertStringContainsString('root directory', $error->getMessage());
			}
		}
		self::assertSame('file.txt', $this->invoke('normalizeFlatFilePath', 'file.txt'));
	}

	public function testDirectoryStatContainsNoSyntheticFileMetadata(): void {
		self::assertSame(['type' => 'dir', 'size' => 0, 'modified' => null], $this->invoke('directoryStat'));
	}

	private function invoke(string $method, mixed ...$args): mixed {
		$instance = (new ReflectionClass(Base3IliasFileStorage::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(Base3IliasFileStorage::class, $method))->invoke($instance, ...$args);
	}
}
