<?php declare(strict_types=1);

define('DIR_ILIAS', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('DIR_TMP', '/srv/www/data/ilias10/base3/'); // TODO: ggf. in config auslagern
define('DIR_SRC', DIR_ILIAS . 'components/Base3/Base3Framework/src/');
define('DIR_TEST', DIR_ILIAS . 'components/Base3/Base3Framework/test/');
define('DIR_PLUGIN', DIR_ILIAS . 'components/Base3/');
define('DIR_LOCAL', DIR_TMP);

require_once DIR_ILIAS . 'vendor/composer/vendor/autoload.php';

include DIR_ILIAS . 'components/Base3/Base3Ilias/src/Base3IliasBootstrap.php';
(new \Base3Ilias\Base3IliasBootstrap())->run();
