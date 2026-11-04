<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasManagedFileStorageService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class Base3IliasManagedFileStorageServicePathTest extends TestCase {

	public function testNormalizePathCollapsesSeparatorsAndDotsWithoutAllowingParentTraversal(): void {
		self::assertSame('', Base3IliasManagedFileStorageService::normalizePath('/'));
		self::assertSame('', Base3IliasManagedFileStorageService::normalizePath('.'));
		self::assertSame('docs/notes/file.pdf', Base3IliasManagedFileStorageService::normalizePath(' /docs\\notes//./file.pdf '));
		self::assertSame('docs/file.pdf', Base3IliasManagedFileStorageService::joinPath('/docs', 'file.pdf'));
		self::assertSame('docs', Base3IliasManagedFileStorageService::joinPath('/docs', '.'));
		self::assertSame('notes', Base3IliasManagedFileStorageService::joinPath('', '/notes'));
	}

	public function testRejectsParentPathSegments(): void {
		$this->expectException(InvalidArgumentException::class);
		Base3IliasManagedFileStorageService::normalizePath('docs/../secret');
	}

	public function testRejectsNullBytes(): void {
		$this->expectException(InvalidArgumentException::class);
		Base3IliasManagedFileStorageService::normalizePath("docs/file\0.pdf");
	}
}
