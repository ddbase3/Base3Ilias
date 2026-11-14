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

use Base3\Base3Ilias\Base3\Base3IliasClassMap;
use Base3\Base3Ilias\Base3\Base3IliasRuntime;
use Base3\Base3Ilias\Base3IliasModuleRegistry;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

require_once dirname(__DIR__, 2) . '/classes/Base3IliasModuleRegistry.php';

final class Base3IliasClassMapModuleScanTest extends TestCase {

	public function testScansNestedModuleClassesWithoutIncludingAbstractOrMismatchedFiles(): void {
		$root = sys_get_temp_dir() . '/base3ilias-classmap-' . bin2hex(random_bytes(8));
		$namespace = 'Base3\\Base3Ilias\\Test\\Fixture\\Scan' . bin2hex(random_bytes(6));
		mkdir($root . '/Sub', 0700, true);

		try {
			file_put_contents($root . '/RootTestSubject.php', "<?php namespace $namespace; final class RootTestSubject {}\n");
			file_put_contents($root . '/Sub/NestedTestSubject.php', "<?php namespace $namespace\\Sub; final class NestedTestSubject {}\n");
			file_put_contents($root . '/AbstractTestSubject.php', "<?php namespace $namespace; abstract class AbstractTestSubject {}\n");
			file_put_contents($root . '/MismatchedTestSubject.php', "<?php namespace $namespace; final class OtherTestSubject {}\n");
			file_put_contents($root . '/Invalid.name.php', "<?php namespace $namespace; final class Invalid {}\n");
			file_put_contents($root . '/Readme.txt', 'ignored');

			$map = (new ReflectionClass(Base3IliasClassMap::class))->newInstanceWithoutConstructor();
			$classes = [];
			(new ReflectionMethod(Base3IliasClassMap::class, 'scanModuleClasses'))->invokeArgs(
				$map, [&$classes, $root, $namespace]
			);

			self::assertCount(2, $classes);
			self::assertEqualsCanonicalizing([
				$namespace . '\\RootTestSubject',
				$namespace . '\\Sub\\NestedTestSubject'
			], array_column($classes, 'class'));
			self::assertEqualsCanonicalizing([
				$root . '/RootTestSubject.php',
				$root . '/Sub/NestedTestSubject.php'
			], array_column($classes, 'file'));
			foreach ($classes as $entry) {
				self::assertSame([], $entry['interfaces']);
			}
		}
		finally {
			$this->removeFixture($root);
		}
	}

	public function testModuleScannerRetainsImplementedInterfacesAndScansOnlyRequestedSubtree(): void {
		$root = sys_get_temp_dir() . '/base3ilias-interface-scan-' . bin2hex(random_bytes(8));
		$namespace = 'Qualitus\\Test\\Fixture\\Interfaces' . bin2hex(random_bytes(6));
		mkdir($root . '/Deep', 0700, true);

		try {
			file_put_contents($root . '/Outside.php', "<?php namespace $namespace; final class Outside {}\n");
			file_put_contents($root . '/Deep/StringableSubject.php',
				"<?php namespace $namespace\\Deep; final class StringableSubject implements \\Stringable { public function __toString(): string { return 'value'; } }\n");
			file_put_contents($root . '/Deep/OnlyInterface.php',
				"<?php namespace $namespace\\Deep; interface OnlyInterface {}\n");

			$map = (new ReflectionClass(Base3IliasClassMap::class))->newInstanceWithoutConstructor();
			$classes = [];
			(new ReflectionMethod(Base3IliasClassMap::class, 'scanModuleClasses'))->invokeArgs(
				$map, [&$classes, $root . '/', $namespace, 'Deep']
			);

			self::assertCount(1, $classes);
			self::assertSame($namespace . '\\Deep\\StringableSubject', $classes[0]['class']);
			self::assertSame($root . '/Deep/StringableSubject.php', $classes[0]['file']);
			self::assertArrayHasKey(\Stringable::class, $classes[0]['interfaces']);
		}
		finally {
			$this->removeFixture($root);
		}
	}

	public function testEmptyModuleDirectoryProducesNoClassMapEntries(): void {
		$root = sys_get_temp_dir() . '/base3ilias-empty-scan-' . bin2hex(random_bytes(8));
		mkdir($root, 0700, true);

		try {
			file_put_contents($root . '/ignored.txt', 'no PHP classes');
			$classes = [];
			$map = (new ReflectionClass(Base3IliasClassMap::class))->newInstanceWithoutConstructor();
			(new ReflectionMethod(Base3IliasClassMap::class, 'scanModuleClasses'))->invokeArgs(
				$map, [&$classes, $root, 'Qualitus\\Test\\MissingNamespace']
			);
			self::assertSame([], $classes);
		}
		finally {
			$this->removeFixture($root);
		}
	}

	public function testIliasRegistrySuppliesBothBase3AndQualitusScanTargets(): void {
		$root = sys_get_temp_dir() . '/base3ilias-vendors-' . bin2hex(random_bytes(8));
		$components = $root . '/components';
		$base3Root = $components . '/Base3/Core';
		$qualitusRoot = $components . '/Qualitus/KimChat';
		mkdir($base3Root . '/src/Sub', 0700, true);
		mkdir($qualitusRoot . '/src/Sub', 0700, true);

		$registryProperty = new ReflectionProperty(Base3IliasRuntime::class, 'moduleRegistry');
		$previousRegistry = $registryProperty->getValue();

		try {
			$this->writeManifest($base3Root, 'Core', 'Base3\\Core');
			$this->writeManifest($qualitusRoot, 'KimChat', 'Qualitus\\KimChat');
			file_put_contents($base3Root . '/src/Sub/CoreSubject.php', '<?php namespace Base3\\Core\\Sub; final class CoreSubject implements \\Stringable { public function __toString(): string { return "core"; } }');
			file_put_contents($qualitusRoot . '/src/Sub/QualitusSubject.php', '<?php namespace Qualitus\\KimChat\\Sub; final class QualitusSubject implements \\Stringable { public function __toString(): string { return "qualitus"; } }');

			$registryProperty->setValue(null, new Base3IliasModuleRegistry($root, [$components]));
			$map = (new ReflectionClass(Base3IliasClassMap::class))->newInstanceWithoutConstructor();
			$targets = (new ReflectionMethod(Base3IliasClassMap::class, 'getScanTargets'))->invoke($map);

			self::assertCount(2, $targets);
			$byModule = array_column($targets, null, 'module');
			self::assertSame('Base3\\Core', $byModule['Core']['namespace']);
			self::assertSame($base3Root . '/src', $byModule['Core']['srcPath']);
			self::assertSame('Qualitus\\KimChat', $byModule['KimChat']['namespace']);
			self::assertSame($qualitusRoot . '/src', $byModule['KimChat']['srcPath']);

			foreach ($byModule as $name => $target) {
				$classes = [];
				(new ReflectionMethod(Base3IliasClassMap::class, 'scanModuleClasses'))->invokeArgs(
					$map, [&$classes, $target['srcPath'], $target['namespace']]
				);
				self::assertCount(1, $classes, $name);
				self::assertArrayHasKey(\Stringable::class, $classes[0]['interfaces']);
			}
		}
		finally {
			$registryProperty->setValue(null, $previousRegistry);
			$this->removeFixture($root);
		}
	}

	private function writeManifest(string $root, string $name, string $namespace): void {
		file_put_contents($root . '/base3.json', json_encode([
			'manifestVersion' => 1,
			'name' => $name,
			'namespace' => $namespace,
			'version' => '1.0.0'
		], JSON_THROW_ON_ERROR));
	}

	private function removeFixture(string $root): void {
		if (!is_dir($root)) return;
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($files as $file) {
			if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
			else unlink($file->getPathname());
		}
		rmdir($root);
	}
}
