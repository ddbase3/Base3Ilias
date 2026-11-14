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


namespace Base3\Base3Ilias\Test\Ilias;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class PublicBase3EndpointTest extends TestCase {

	public function testCopiedPublicEndpointUsesIliasComposerWithoutCoreLauncher(): void {
		$root = $this->newIliasRoot();
		try {
			$this->makeDirectory($root . '/vendor/composer/vendor');
			file_put_contents($root . '/ilias.ini.php', '[clients]');
			file_put_contents($root . '/vendor/composer/vendor/autoload.php', <<<'PHP_SOURCE'
<?php declare(strict_types=1);
namespace Base3\Base3Ilias\Base3;
final class Base3IliasRuntime {
	public static function bootStandaloneAndDispatch(bool $preserveRequest): string {
		return $preserveRequest ? 'public-base3-ready' : 'request-not-preserved';
	}
}
PHP_SOURCE
			);
			copy(dirname(__DIR__, 2) . '/resources/base3.php', $root . '/public/base3.php');
			[$status, $out, $error] = $this->executePhpScript($root . '/public/base3.php');
			self::assertSame(0, $status, $error);
			self::assertSame('public-base3-ready', $out);
		} finally {
			$this->removeDirectory($root);
		}
	}

	public function testCopiedPublicEndpointRequiresIliasComposer(): void {
		$root = $this->newIliasRoot();
		try {
			file_put_contents($root . '/ilias.ini.php', '[clients]');
			copy(dirname(__DIR__, 2) . '/resources/base3.php', $root . '/public/base3.php');
			[$status, , $error] = $this->executePhpScript($root . '/public/base3.php');
			self::assertNotSame(0, $status);
			self::assertStringContainsString('ILIAS Composer autoloader not found:', $error);
		} finally {
			$this->removeDirectory($root);
		}
	}

	private function newIliasRoot(): string {
		$root = sys_get_temp_dir() . '/base3-public-endpoint-' . bin2hex(random_bytes(8));
		$this->makeDirectory($root . '/public');
		return $root;
	}

	private function makeDirectory(string $dir): void {
		if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
			throw new \RuntimeException('Could not create fixture directory: ' . $dir);
		}
	}

	/** @return array{int,string,string} */
	private function executePhpScript(string $script): array {
		$process = proc_open([PHP_BINARY, $script], [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w']
		], $pipes);
		if (!is_resource($process)) {
			throw new \RuntimeException('Could not execute PHP fixture.');
		}
		fclose($pipes[0]);
		$out = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		return [proc_close($process), (string) $out, (string) $error];
	}

	private function removeDirectory(string $root): void {
		if (!is_dir($root)) return;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($iterator as $entry) {
			if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname());
			else unlink($entry->getPathname());
		}
		rmdir($root);
	}
}
