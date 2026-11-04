<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasClassMap;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class Base3IliasClassMapModuleScanTest extends TestCase {

	public function testScansNestedModuleClassesWithoutIncludingAbstractOrMismatchedFiles(): void {
		$root = sys_get_temp_dir() . '/base3ilias-classmap-' . bin2hex(random_bytes(8));
		$namespace = 'Base3Ilias\\Test\\Fixture\\Scan' . bin2hex(random_bytes(6));
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
			$arguments = [&$classes, $root, $namespace];
			(new ReflectionMethod(Base3IliasClassMap::class, 'scanModuleClasses'))->invokeArgs($map, $arguments);

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
		} finally {
			foreach (glob($root . '/Sub/*') ?: [] as $file) unlink($file);
			foreach (glob($root . '/*') ?: [] as $file) {
				if (is_file($file)) unlink($file);
			}
			rmdir($root . '/Sub');
			rmdir($root);
		}
	}
	public function testModuleScannerRetainsImplementedInterfacesAndScansOnlyRequestedSubtree(): void {
		$root = sys_get_temp_dir() . '/base3ilias-interface-scan-' . bin2hex(random_bytes(8));
		$namespace = 'Base3Ilias\\Test\\Fixture\\Interfaces' . bin2hex(random_bytes(6));
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
		} finally {
			foreach (glob($root . '/Deep/*') ?: [] as $file) unlink($file);
			foreach (glob($root . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
			rmdir($root . '/Deep');
			rmdir($root);
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
				$map, [&$classes, $root, 'Base3Ilias\\Test\\MissingNamespace']
			);
			self::assertSame([], $classes);
		} finally {
			unlink($root . '/ignored.txt');
			rmdir($root);
		}
	}

}
