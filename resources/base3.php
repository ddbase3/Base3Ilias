<?php declare(strict_types=1);

$path = realpath(__DIR__);
if ($path === false) {
	throw new RuntimeException('BASE3 public endpoint directory could not be resolved.');
}

$iliasRoot = null;
while (true) {
	if (is_file($path . DIRECTORY_SEPARATOR . 'ilias.ini.php')) {
		$iliasRoot = $path;
		break;
	}

	$parent = dirname($path);
	if ($parent === $path) break;
	$path = $parent;
}

if ($iliasRoot === null) {
	throw new RuntimeException('ILIAS root directory could not be resolved.');
}

$composerAutoload = $iliasRoot . DIRECTORY_SEPARATOR . 'vendor/composer/vendor/autoload.php';
if (!is_file($composerAutoload)) {
	throw new RuntimeException('ILIAS Composer autoloader not found: ' . $composerAutoload);
}
require_once $composerAutoload;

echo \Base3\Base3Ilias\Base3\Base3IliasRuntime::bootStandaloneAndDispatch(true);
