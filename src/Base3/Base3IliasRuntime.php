<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Api\IRequest;
use Base3\Api\ISystemService;
use Base3\Configuration\Api\IConfiguration;
use Base3\Core\Autoloader;
use Base3\Core\Request;
use Base3\Core\ServiceLocator;
use Base3\Hook\HookManager;
use Base3\Hook\Api\IHookListener;
use Base3\Hook\Api\IHookManager;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\ServiceSelector\Api\IServiceSelector;
use Base3\ServiceSelector\Standard\StandardServiceSelector;
use Base3Ilias\External\IliasPsrContainer;
use RuntimeException;
use XapiProxy\ilInitialisation;
use ilContext;

class Base3IliasRuntime {

	protected static bool $booted = false;
	protected static bool $finishDispatched = false;
	protected static ?Base3IliasServiceLocator $serviceLocator = null;
	protected static ?IHookManager $hookManager = null;

	public static function bootOnce(bool $bootIliasIfNeeded = false, bool $finishOnBoot = true): Base3IliasServiceLocator {
		if (self::$booted) {
			if ($finishOnBoot) self::dispatchFinish();
			return self::getServiceLocator();
		}

		self::prepareEnvironment();

		$systemService = new Base3IliasSystemService();
		if ($bootIliasIfNeeded && !self::isIliasBooted()) {
			self::initIlias($systemService);
		}

		if (!self::isIliasBooted()) {
			throw new RuntimeException('ILIAS container not available.');
		}

		self::configureDebug();
		self::registerBase3Autoloader();

		$request = Request::fromGlobals();

		$servicelocator = new Base3IliasServiceLocator();
		ServiceLocator::useInstance($servicelocator);
		$servicelocator
			->set('servicelocator', $servicelocator, IContainer::SHARED)
			->set(ISystemService::class, $systemService, IContainer::SHARED)
			->set(IRequest::class, $request, IContainer::SHARED)
			->set(IContainer::class, 'servicelocator', IContainer::ALIAS)
			->set(IHookManager::class, fn() => new HookManager(), ServiceLocator::SHARED)
			->set(IClassMap::class, new Base3IliasClassMap($servicelocator), IContainer::SHARED)
			->set('classmap', IClassMap::class, IContainer::ALIAS)
			->set(IServiceSelector::class, fn() => new StandardServiceSelector($servicelocator), IContainer::SHARED)
			->set(ILinkTargetService::class, fn($c) => new Base3IliasLinkTargetService($c->get(IConfiguration::class)), IContainer::SHARED);

		$servicelocator->setIliasContainer(new IliasPsrContainer($GLOBALS['DIC']));
		$servicelocator->set(\ILIAS\DI\Container::class, $GLOBALS['DIC'], IContainer::SHARED);

		$hookManager = $servicelocator->get(IHookManager::class);
		$listeners = $servicelocator->get(IClassMap::class)->getInstancesByInterface(IHookListener::class);
		foreach ($listeners as $listener) {
			$hookManager->addHookListener($listener);
		}
		$hookManager->dispatch('bootstrap.init');

		$plugins = $servicelocator->get(IClassMap::class)->getInstancesByInterface(IPlugin::class);
		foreach ($plugins as $plugin) {
			$plugin->init();
		}
		$hookManager->dispatch('bootstrap.start');

		self::fillIliasContainer($servicelocator);

		self::$serviceLocator = $servicelocator;
		self::$hookManager = $hookManager;
		self::$booted = true;

		if ($finishOnBoot) self::dispatchFinish();

		return $servicelocator;
	}

	public static function dispatch(): string {
		$servicelocator = self::bootOnce(false, true);
		$serviceselector = $servicelocator->get(IServiceSelector::class);

		return (string) $serviceselector->go();
	}

	public static function bootStandaloneAndDispatch(): string {
		self::bootOnce(true, false);

		$output = self::dispatchInternal();
		self::dispatchFinish();

		return $output;
	}

	public static function getServiceLocator(): Base3IliasServiceLocator {
		if (self::$serviceLocator === null) {
			throw new RuntimeException('Base3Ilias runtime not booted.');
		}

		return self::$serviceLocator;
	}

	protected static function dispatchInternal(): string {
		$serviceselector = self::getServiceLocator()->get(IServiceSelector::class);

		return (string) $serviceselector->go();
	}

	protected static function dispatchFinish(): void {
		if (self::$finishDispatched || self::$hookManager === null) return;

		self::$hookManager->dispatch('bootstrap.finish');
		self::$finishDispatched = true;
	}

	protected static function prepareEnvironment(): void {
		self::defineDirectories();
		self::prepareCliRequest();
		self::loadComposerAutoload();
	}

	protected static function defineDirectories(): void {
		if (!defined('DIR_ILIAS')) {
			define('DIR_ILIAS', realpath(__DIR__ . '/../../../../..') . DIRECTORY_SEPARATOR);
		}

		$iliasConfig = [];
		$configFile = DIR_ILIAS . 'ilias.ini.php';
		if (is_file($configFile)) {
			$parsed = parse_ini_file($configFile, true);
			if (is_array($parsed)) {
				$iliasConfig = $parsed;
			}
		}

		$dataDir = '';
		$clientDir = '';

		if (isset($iliasConfig['clients']['datadir']) && isset($iliasConfig['clients']['default'])) {
			$dataDir = rtrim((string) $iliasConfig['clients']['datadir'], '/\\') . DIRECTORY_SEPARATOR;
			$clientDir = $dataDir . trim((string) $iliasConfig['clients']['default'], '/\\') . DIRECTORY_SEPARATOR;
		}

		if (!defined('DIR_DATA')) define('DIR_DATA', $dataDir);
		if (!defined('DIR_CLIENT')) define('DIR_CLIENT', $clientDir);
		if (!defined('DIR_COMPONENTS')) define('DIR_COMPONENTS', DIR_ILIAS . 'components/');
		if (!defined('DIR_BASE3')) define('DIR_BASE3', DIR_COMPONENTS . 'Base3/');
		if (!defined('DIR_FRAMEWORK')) define('DIR_FRAMEWORK', DIR_BASE3 . 'Base3Framework/');
		if (!defined('DIR_SRC')) define('DIR_SRC', DIR_FRAMEWORK . 'src/');
		if (!defined('DIR_TEST')) define('DIR_TEST', DIR_FRAMEWORK . 'test/');
		if (!defined('DIR_PLUGIN')) define('DIR_PLUGIN', DIR_BASE3);
		if (!defined('DIR_TMP')) define('DIR_TMP', DIR_BASE3 . 'tmp/');
		if (!defined('DIR_LOCAL')) define('DIR_LOCAL', DIR_TMP);
	}

	protected static function prepareCliRequest(): void {
		if (PHP_SAPI !== 'cli') return;

		$_SERVER['HTTP_HOST'] ??= 'localhost';
		$_SERVER['REQUEST_URI'] ??= '/';
		$_SERVER['HTTPS'] ??= 'off';
	}

	protected static function loadComposerAutoload(): void {
		$autoload = DIR_ILIAS . 'vendor/composer/vendor/autoload.php';
		if (is_file($autoload)) {
			require_once $autoload;
		}
	}

	protected static function configureDebug(): void {
		if (getenv('DEBUG') === false) {
			putenv('DEBUG=1');
		}

		$debug = getenv('DEBUG');
		$enabled = $debug !== false && $debug !== '0' && $debug !== '';

		ini_set('display_errors', $enabled ? '1' : '0');
		ini_set('display_startup_errors', $enabled ? '1' : '0');
		error_reporting($enabled ? E_ALL | E_STRICT : 0);
	}

	protected static function registerBase3Autoloader(): void {
		$autoloaderFile = DIR_SRC . 'Core/Autoloader.php';
		if (!is_file($autoloaderFile)) {
			throw new RuntimeException('Base3 autoloader not found: ' . $autoloaderFile);
		}

		require_once $autoloaderFile;
		Autoloader::register();
	}

	protected static function isIliasBooted(): bool {
		return isset($GLOBALS['DIC']) && $GLOBALS['DIC'] instanceof \ILIAS\DI\Container;
	}

	protected static function initIlias(Base3IliasSystemService $systemService): void {
		$iliasVersion = $systemService->getHostSystemVersion();

		if (version_compare($iliasVersion, '11.0', '<')) {
			self::initIlias10();
			return;
		}

		self::initIlias11();
	}

	protected static function initIlias10(): void {
		switch (true) {
			case isset($_REQUEST['rest']):
				ilContext::init(ilContext::CONTEXT_REST);
				break;

			default:
				ilContext::init(ilContext::CONTEXT_WEB);
		}

		ilInitialisation::initILIAS();
	}

	protected static function initIlias11(): void {
		require_once DIR_ILIAS . 'artifacts/bootstrap_default.php';
		entry_point('ILIAS Legacy Initialisation Adapter');
	}

	protected static function fillIliasContainer(Base3IliasServiceLocator $servicelocator): void {
		$services = $servicelocator->getServiceList();

		foreach ($services as $service) {
			if (isset($GLOBALS['DIC'][$service])) continue;
			$GLOBALS['DIC'][$service] = $servicelocator->get($service);
		}
	}
}
