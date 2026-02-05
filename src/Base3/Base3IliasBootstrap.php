<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\IBootstrap;
use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Api\IRequest;
use Base3\Api\ISystemService;
use Base3\Core\Autoloader;
use Base3\Core\PluginClassMap;
use Base3\Core\Request;
use Base3\Core\ServiceLocator;
use Base3\Hook\IHookManager;
use Base3\Hook\IHookListener;
use Base3\Hook\HookManager;
use Base3\ServiceSelector\Api\IServiceSelector;
use Base3\ServiceSelector\Standard\StandardServiceSelector;
use Base3Ilias\External\IliasPsrContainer;
use XapiProxy\ilInitialisation;
use ilContext;

class Base3IliasBootstrap implements IBootstrap {

	public function run(): void {

		// handle request vars
		$request = Request::fromGlobals();

		// check system
		$systemService = new Base3IliasSystemService();
		$iliasVersion = $systemService->getHostSystemVersion();

		// ilias bootstrap
		if (!isset($_REQUEST['noilias'])) {
			if (version_compare($iliasVersion, "11.0", "<")) {
				$this->initIlias10();
			} else {
				$this->initIlias11();
			}
		}

		// Debug mode - 0: off, 1: on
		putenv('DEBUG=1');

		// error handling
		ini_set('display_errors', getenv('DEBUG') ? 1 : 0);
		ini_set('display_startup_errors', getenv('DEBUG') ? 1 : 0);
		error_reporting(getenv('DEBUG') ? E_ALL | E_STRICT : 0);

		// autoloader
		require DIR_SRC . 'Core/Autoloader.php';
		Autoloader::register();

		// service locator
		$servicelocator = new Base3IliasServiceLocator();
		ServiceLocator::useInstance($servicelocator);
		$servicelocator
			->set('servicelocator', $servicelocator, IContainer::SHARED)
			->set(ISystemService::class, $systemService, IContainer::SHARED)
			->set(IRequest::class, $request, IContainer::SHARED)
			->set(IContainer::class, 'servicelocator', IContainer::ALIAS)
			->set(IHookManager::class, fn() => new HookManager, ServiceLocator::SHARED)
			->set(IClassMap::class, new Base3IliasClassMap($servicelocator), IContainer::SHARED)
			->set('classmap', IClassMap::class, IContainer::ALIAS)
			->set(IServiceSelector::class, fn() => new StandardServiceSelector($servicelocator), IContainer::SHARED);

		// fill container with ILIAS services
		$servicelocator->setIliasContainer(new IliasPsrContainer($GLOBALS['DIC']));
		$servicelocator->set(\ILIAS\DI\Container::class, $GLOBALS['DIC'], IContainer::SHARED);

		// hooks
		$hookManager = $servicelocator->get(IHookManager::class);
		$listeners = $servicelocator->get(IClassMap::class)->getInstancesByInterface(IHookListener::class);
		foreach ($listeners as $listener) $hookManager->addHookListener($listener);
		$hookManager->dispatch('bootstrap.init');

		// plugins
		$plugins = $servicelocator->get(IClassMap::class)->getInstancesByInterface(IPlugin::class);
		foreach ($plugins as $plugin) $plugin->init();
		$hookManager->dispatch('bootstrap.start');

		// go
		$serviceselector = $servicelocator->get(IServiceSelector::class);
		echo $serviceselector->go();
		$hookManager->dispatch('bootstrap.finish');
	}

	private function initIlias10() {
		switch (true) {
			case isset($_REQUEST['rest']):
				ilContext::init(ilContext::CONTEXT_REST);
				break;
			default:
				ilContext::init(ilContext::CONTEXT_WEB);
		}
		ilInitialisation::initILIAS();
	}

	private function initIlias11() {
		require_once DIR_ILIAS . '/artifacts/bootstrap_default.php';
		entry_point('ILIAS Legacy Initialisation Adapter');
	}
}
