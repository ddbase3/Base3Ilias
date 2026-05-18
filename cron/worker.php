<?php declare(strict_types=1);

$publicPath = realpath(__DIR__ . '/../../../../public');

if ($publicPath === false) {
	fwrite(STDERR, "Could not resolve public path.\n");
	exit(1);
}

chdir($publicPath);

$query = [
	'baseClass' => 'ilUIPluginRouterGUI',
	'cmdClass' => 'ilBase3IliasAdapterAjaxGUI',
	'cmd' => 'dispatch',
	'name' => 'masterworker'
];

$queryString = http_build_query($query);

$_GET = $query;
$_POST = [];
$_REQUEST = $query;

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['QUERY_STRING'] = $queryString;
$_SERVER['REQUEST_URI'] = '/ilias.php?' . $queryString;

$_SERVER['SCRIPT_FILENAME'] = $publicPath . '/ilias.php';
$_SERVER['SCRIPT_NAME'] = '/ilias.php';
$_SERVER['PHP_SELF'] = '/ilias.php';
$_SERVER['DOCUMENT_ROOT'] = $publicPath;

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$_SERVER['SERVER_PORT'] = '443';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_SCHEME'] = 'https';

$_SERVER['HTTP_USER_AGENT'] = 'base3-worker-cli';
$_SERVER['HTTP_ACCEPT'] = '*/*';

require $publicPath . '/ilias.php';
