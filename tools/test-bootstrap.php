<?php declare(strict_types=1);

use Base3\Base3Ilias\Base3IliasModuleRegistry;
use Composer\Autoload\ClassLoader;

$iliasRoot = getenv('BASE3_ILIAS_TEST_ROOT');
if (!is_string($iliasRoot) || $iliasRoot === '' || !is_dir($iliasRoot)) {
	throw new RuntimeException('ILIAS test root is not available.');
}

$composerLoader = require $iliasRoot . '/vendor/composer/vendor/autoload.php';
if (!$composerLoader instanceof ClassLoader) {
	throw new RuntimeException('ILIAS Composer autoloader is not available.');
}

require_once dirname(__DIR__) . '/classes/Base3IliasModuleRegistry.php';

$modules = new Base3IliasModuleRegistry($iliasRoot, [$iliasRoot . '/components']);
foreach ($modules->getModules() as $module) {
	$path = $modules->resolveModulePath($module);
	$namespace = rtrim($module['namespace'], '\\') . '\\';

	if (is_dir($path . '/src')) {
		$composerLoader->addPsr4($namespace, $path . '/src');
	}
	if (is_dir($path . '/test')) {
		$composerLoader->addPsr4($namespace . 'Test\\', $path . '/test');
	}
}
