<?php declare(strict_types=1);

use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Configuration\Api\IConfiguration;
use Base3\Core\Autoloader;
use Base3\Core\PluginClassMap;
use Base3\Core\ServiceLocator;
use Base3\ServiceSelector\Api\IServiceSelector;
use Base3\ServiceSelector\Standard\StandardServiceSelector;
use Base3Ilias\Base3IliasConfiguration;
use XapiProxy\ilInitialisation;

ilInitialisation::initILIAS();

/* Debug mode - 0: aus, 1: an, ggfs noch höhere Stufen? */
putenv('DEBUG=1');

/* error handling */
ini_set('display_errors', getenv('DEBUG') ? 1 : 0);
ini_set('display_startup_errors', getenv('DEBUG') ? 1 : 0);
error_reporting(getenv('DEBUG') ? E_ALL | E_STRICT : 0);

/* define directories constants */
const DIR_TMP = '/srv/www/data/ilias10/base3/';  // TODO config
const DIR_SRC = DIR_ILIAS . '/components/Base3/Base3Framework/src/';
const DIR_TEST = DIR_ILIAS . '/components/Base3/Base3Framework/test/';
const DIR_PLUGIN = DIR_ILIAS . '/components/Base3/';
const DIR_LOCAL = DIR_TMP;

/* autoloader */
require DIR_SRC . 'Core/Autoloader.php';
Autoloader::register();

/* service locator */
$servicelocator = new ServiceLocator();
ServiceLocator::useInstance($servicelocator);
$servicelocator
    ->set('servicelocator', $servicelocator, IContainer::SHARED)
    ->set(IContainer::class, 'servicelocator', IContainer::ALIAS)
    ->set('configuration', new Base3IliasConfiguration, IContainer::SHARED)
    ->set(IConfiguration::class, 'configuration', IContainer::ALIAS)
    ->set('classmap', new PluginClassMap($servicelocator), IContainer::SHARED)
    ->set(IClassMap::class, 'classmap', IContainer::ALIAS)
    ->set(IServiceSelector::class, StandardServiceSelector::getInstance(), ServiceLocator::SHARED);

/* plugins */
$plugins = $servicelocator->get(IClassMap::class)->getInstancesByInterface(IPlugin::class);
foreach ($plugins as $plugin) $plugin->init();

/* go */
$serviceselector = $servicelocator->get(IServiceSelector::class);
$serviceselector->go();
