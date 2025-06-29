<?php declare(strict_types=1);

// ILIAS base dir
define('DIR_ILIAS', realpath(__DIR__ . '/..') . '/');

// Generate data dir and tmp dir from ini (Remember to create DIR_TMP!)
$iliasConfig = parse_ini_file(DIR_ILIAS . 'ilias.ini.php', true);
$dataDir = $clientDir = $tmpDir = '';
if (isset($iliasConfig['clients']) && isset($iliasConfig['clients']['datadir']) && isset($iliasConfig['clients']['default'])) {
	$dataDir = $iliasConfig['clients']['datadir'] . '/';
	$clientDir = $dataDir . $iliasConfig['clients']['default'] . '/';
	$tmpDir = $clientDir . 'base3/';
}
define('DIR_DATA', $dataDir);
define('DIR_CLIENT', $clientDir);
define('DIR_TMP', $tmpDir);

// installation dirs
define('DIR_COMPONENTS', DIR_ILIAS . 'components/');
define('DIR_BASE3', DIR_COMPONENTS . 'Base3/');
define('DIR_FRAMEWORK', DIR_BASE3 . 'Base3Framework/');
define('DIR_SRC', DIR_FRAMEWORK . 'src/');
define('DIR_TEST', DIR_FRAMEWORK . 'test/');
define('DIR_PLUGIN', DIR_BASE3);
define('DIR_LOCAL', DIR_TMP);

// autoload
require_once DIR_ILIAS . 'vendor/composer/vendor/autoload.php';

// bootstrap
include DIR_BASE3 . 'Base3Ilias/src/Base3IliasBootstrap.php';
(new \Base3Ilias\Base3IliasBootstrap())->run();

