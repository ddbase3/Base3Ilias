<?php declare(strict_types=1);

$moduleRoot = dirname(__DIR__);
$path = realpath($moduleRoot);
if ($path === false) {
	throw new RuntimeException('Base3Ilias module root could not be resolved.');
}

$iliasRoot = null;
$current = $path;
while (true) {
	if (is_file($current . DIRECTORY_SEPARATOR . 'ilias.ini.php')) {
		$iliasRoot = $current;
		break;
	}

	$parent = dirname($current);
	if ($parent === $current) break;
	$current = $parent;
}

if ($iliasRoot === null) {
	throw new RuntimeException('ILIAS root directory could not be resolved.');
}

$composerAutoload = $iliasRoot . DIRECTORY_SEPARATOR . 'vendor/composer/vendor/autoload.php';
if (!is_file($composerAutoload)) {
	throw new RuntimeException('ILIAS Composer autoloader not found: ' . $composerAutoload);
}
require_once $composerAutoload;

$moduleRegistryFile = $moduleRoot . DIRECTORY_SEPARATOR . 'classes/Base3IliasModuleRegistry.php';
if (!is_file($moduleRegistryFile)) {
	throw new RuntimeException('Base3Ilias module registry not found: ' . $moduleRegistryFile);
}
require_once $moduleRegistryFile;

$registry = new \Base3\Base3Ilias\Base3IliasModuleRegistry(
	$iliasRoot,
	[$iliasRoot . DIRECTORY_SEPARATOR . 'components']
);

$frameworkRoot = $registry->requireModulePath('Base3Framework');
$base3IliasRoot = $registry->requireModulePath('Base3Ilias');

$prefixes = [
	'Base3\\' => $frameworkRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR,
	'Base3Ilias\\' => $base3IliasRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
];

$bootstrapAutoloader = static function(string $class) use ($prefixes): void {
	foreach ($prefixes as $prefix => $sourceRoot) {
		if (!str_starts_with($class, $prefix)) continue;

		$relativeClass = substr($class, strlen($prefix));
		$file = $sourceRoot . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
		if (is_file($file)) {
			require_once $file;
		}
		return;
	}
};

spl_autoload_register($bootstrapAutoloader, true, true);

try {
	echo \Base3Ilias\Base3\Base3IliasRuntime::bootStandaloneAndDispatch(true);
}
finally {
	spl_autoload_unregister($bootstrapAutoloader);
}
