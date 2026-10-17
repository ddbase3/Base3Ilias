<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class Base3IliasRuntimeModuleDiscoveryTest extends TestCase {

	public function testEarlyRuntimeBootFindsModulesInLegacyPluginLib(): void {
		$result = $this->probeModuleDiscovery('legacy');

		self::assertSame(['Base3Framework', 'Base3Ilias', 'Chatbot'], $result['modules']);
		self::assertSame($result['moduleParent'], $result['base3']);
		self::assertSame($result['moduleParent'], $result['plugin']);
		self::assertSame($result['moduleParent'] . 'Base3Framework/', $result['framework']);
	}

	public function testModernRuntimeStillDiscoversAllComponents(): void {
		$result = $this->probeModuleDiscovery('modern');

		self::assertSame(['Base3Framework', 'Base3Ilias', 'OtherModule'], $result['modules']);
		self::assertSame($result['moduleParent'], $result['base3']);
		self::assertSame($result['moduleParent'], $result['plugin']);
		self::assertSame($result['moduleParent'] . 'Base3Framework/', $result['framework']);
	}

	public function testNestedComponentLibraryRetainsFullComponentDiscovery(): void {
		$result = $this->probeModuleDiscovery('nested');

		self::assertSame(['Base3Framework', 'Base3Ilias', 'OtherModule'], $result['modules']);
		self::assertSame($result['moduleParent'], $result['base3']);
		self::assertSame($result['moduleParent'], $result['plugin']);
		self::assertSame($result['moduleParent'] . 'Base3Framework/', $result['framework']);
	}

	private function probeModuleDiscovery(string $layout): array {
		$fixtureRoot = sys_get_temp_dir() . '/base3_discovery_' . bin2hex(random_bytes(8));
		$iliasRoot = $fixtureRoot . '/ilias';
		$moduleParent = $iliasRoot . match ($layout) {
			'legacy' => '/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Base3IliasAdapter/lib/',
			'modern' => '/components/Base3/',
			'nested' => '/components/Qualitus/Base3Core/lib/'
		};
		$moduleRoot = $moduleParent . 'Base3Ilias';
		$packageRoot = dirname(__DIR__, 2);

		try {
			$this->makeDirectory($moduleRoot . '/src/Base3');
			$this->makeDirectory($moduleRoot . '/classes');
			$this->makeDirectory($moduleParent . 'Base3Framework');

			$this->copyFile(
				$packageRoot . '/src/Base3/Base3IliasRuntime.php',
				$moduleRoot . '/src/Base3/Base3IliasRuntime.php'
			);
			$this->copyFile(
				$packageRoot . '/classes/Base3IliasModuleRegistry.php',
				$moduleRoot . '/classes/Base3IliasModuleRegistry.php'
			);

			$this->writeManifest($moduleRoot, 'Base3Ilias', 'Base3Ilias');
			$this->writeManifest($moduleParent . 'Base3Framework', 'Base3Framework', 'Base3');

			if ($layout !== 'legacy') {
				$this->writeManifest($iliasRoot . '/components/OtherVendor/OtherModule', 'OtherModule', 'OtherModule');
			} else {
				$this->writeManifest($moduleParent . 'Chatbot', 'Chatbot', 'Chatbot');
			}

			$this->makeDirectory($iliasRoot);
			$ini = '[clients]' . "\n"
				. 'datadir = ' . json_encode($fixtureRoot . '/data') . "\n"
				. 'default = client' . "\n";
			file_put_contents($iliasRoot . '/ilias.ini.php', $ini);

			$probe = <<<'PHP'
<?php declare(strict_types=1);
require __DIR__ . '/Base3Ilias/classes/Base3IliasModuleRegistry.php';
require __DIR__ . '/Base3Ilias/src/Base3/Base3IliasRuntime.php';
$method = new ReflectionMethod(\Base3Ilias\Base3\Base3IliasRuntime::class, 'defineDirectories');
$method->invoke(null);
echo json_encode([
    'modules' => array_keys(\Base3Ilias\Base3\Base3IliasRuntime::getBase3Modules()),
    'base3' => DIR_BASE3,
    'plugin' => DIR_PLUGIN,
    'framework' => DIR_FRAMEWORK
], JSON_THROW_ON_ERROR);
PHP;
			// The fixture bootstrap is placed beside Base3Ilias, matching both layouts.
			$probeFile = $moduleParent . 'probe.php';
			file_put_contents($probeFile, $probe);

			$output = [];
			$exitCode = 0;
			exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probeFile) . ' 2>&1', $output, $exitCode);
			self::assertSame(0, $exitCode, implode("\n", $output));

			$result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
			self::assertIsArray($result);
			$result['moduleParent'] = $moduleParent;
			return $result;
		}
		finally {
			$this->removeDirectory($fixtureRoot);
		}
	}

	private function writeManifest(string $directory, string $name, string $namespace): void {
		$this->makeDirectory($directory);
		file_put_contents($directory . '/base3.json', json_encode([
			'manifestVersion' => 1,
			'name' => $name,
			'namespace' => $namespace,
			'version' => '1.0.0'
		], JSON_THROW_ON_ERROR));
	}

	private function copyFile(string $source, string $target): void {
		if (!copy($source, $target)) {
			throw new RuntimeException('Could not copy test fixture: ' . $source);
		}
	}

	private function makeDirectory(string $directory): void {
		if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
			throw new RuntimeException('Could not create test directory: ' . $directory);
		}
	}

	private function removeDirectory(string $directory): void {
		if (!is_dir($directory)) return;

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($iterator as $path) {
			if ($path->isDir()) rmdir($path->getPathname());
			else unlink($path->getPathname());
		}
		rmdir($directory);
	}
}
