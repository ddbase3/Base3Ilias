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
use Base3\Migration\Api\IMigrationRunner;
use Base3\Migration\No\NoMigrationRunner;
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

	/**
	 * Snapshot used by standalone public endpoints that must survive the ILIAS
	 * bootstrap. ILIAS may rewrite request globals while it initializes its own
	 * legacy request handling. For normal embedded Base3 usage this remains null.
	 *
	 * @var array<string,mixed>|null
	 */
	protected static ?array $standaloneRequestSnapshot = null;

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

		if (self::$standaloneRequestSnapshot !== null) {
			/**
			 * This is the important part for public standalone endpoints such as
			 * mcp.php: capture happened before ILIAS booted, restore happens after
			 * ILIAS booted, and only then do we create the Base3 IRequest service.
			 *
			 * This keeps the workaround in Base3Ilias instead of pushing host-system
			 * knowledge into applications such as MissionBay.
			 */
			self::restoreRequestSnapshot(self::$standaloneRequestSnapshot);
			$request = self::createRequestFromSnapshot(self::$standaloneRequestSnapshot);
		}
		else {
			$request = Request::fromGlobals();
		}

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
			->set(IMigrationRunner::class, fn() => new NoMigrationRunner(), IContainer::SHARED)
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

		$servicelocator->get(IMigrationRunner::class)->migrate();
		$hookManager->dispatch('bootstrap.migrated');

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

	/**
	 * Boots ILIAS/Base3 and dispatches the Base3 output selected by the request.
	 *
	 * $preserveRequest is intended for standalone public endpoint files that are
	 * executed before Base3 exists, for example public/mcp.php. Those endpoints
	 * need ILIAS to boot, but they also need the original raw request body and
	 * query parameters to survive the ILIAS bootstrap.
	 *
	 * When enabled, the original request globals and raw php://input are captured
	 * before ILIAS is initialized. After ILIAS finished booting, the snapshot is
	 * restored before Base3 builds its IRequest service. This avoids MCP-specific
	 * or host-specific hacks inside application plugins.
	 */
	public static function bootStandaloneAndDispatch(bool $preserveRequest = false): string {
		$snapshot = $preserveRequest ? self::captureRequestSnapshot() : null;

		if ($snapshot !== null) {
			self::$standaloneRequestSnapshot = $snapshot;
		}

		try {
			self::bootOnce(true, false);

			if ($snapshot !== null && self::$serviceLocator !== null) {
				/**
				 * If bootOnce() returned an already booted runtime, it could not rebuild
				 * the IRequest service before dispatching. Reinstall the preserved
				 * request here as a safety net for repeated standalone dispatches in the
				 * same PHP process, such as tests or long-running runners.
				 */
				self::restoreRequestSnapshot($snapshot);
				self::$serviceLocator->set(IRequest::class, self::createRequestFromSnapshot($snapshot), IContainer::SHARED);
			}

			$output = self::dispatchInternal();
			self::dispatchFinish();

			return $output;
		}
		finally {
			self::$standaloneRequestSnapshot = null;
		}
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

		if ($clientDir === '') {
			throw new RuntimeException('ILIAS client data directory could not be resolved.');
		}

		if (!defined('DIR_DATA')) define('DIR_DATA', $dataDir);
		if (!defined('DIR_CLIENT')) define('DIR_CLIENT', $clientDir);
		if (!defined('DIR_COMPONENTS')) define('DIR_COMPONENTS', DIR_ILIAS . 'components/');
		if (!defined('DIR_BASE3')) define('DIR_BASE3', DIR_COMPONENTS . 'Base3/');
		if (!defined('DIR_FRAMEWORK')) define('DIR_FRAMEWORK', DIR_BASE3 . 'Base3Framework/');
		if (!defined('DIR_SRC')) define('DIR_SRC', DIR_FRAMEWORK . 'src/');
		if (!defined('DIR_TEST')) define('DIR_TEST', DIR_FRAMEWORK . 'test/');
		if (!defined('DIR_PLUGIN')) define('DIR_PLUGIN', DIR_BASE3);

		if (!defined('DIR_BASE3_DATA')) define('DIR_BASE3_DATA', DIR_CLIENT . 'base3' . DIRECTORY_SEPARATOR);
		if (!defined('DIR_BASE3_ARTIFACTS')) define('DIR_BASE3_ARTIFACTS', DIR_BASE3_DATA . 'artifacts' . DIRECTORY_SEPARATOR);
		if (!defined('DIR_BASE3_CACHE')) define('DIR_BASE3_CACHE', DIR_BASE3_DATA . 'cache' . DIRECTORY_SEPARATOR);

		self::ensureDirectory(DIR_BASE3_DATA);
		self::ensureDirectory(DIR_BASE3_ARTIFACTS);
		self::ensureDirectory(DIR_BASE3_CACHE);

		if (!defined('DIR_TMP')) define('DIR_TMP', DIR_BASE3_ARTIFACTS);
		if (!defined('DIR_LOCAL')) define('DIR_LOCAL', DIR_BASE3_DATA);
	}

	protected static function ensureDirectory(string $path): void {
		if ($path === '') {
			throw new RuntimeException('Directory path is empty.');
		}

		if (is_dir($path)) {
			return;
		}

		if (!mkdir($path, 0770, true) && !is_dir($path)) {
			throw new RuntimeException('Could not create directory: ' . $path);
		}
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
			putenv('DEBUG=0');
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

	/**
	 * Capture request state before ILIAS has a chance to normalize or sanitize it.
	 *
	 * Important: the raw request body cannot be reconstructed from superglobals.
	 * This is why php://input is copied here and later served through the preserved
	 * IRequest implementation used for standalone endpoints.
	 *
	 * @return array<string,mixed>
	 */
	protected static function captureRequestSnapshot(): array {
		$rawInput = file_get_contents('php://input');

		return [
			'get' => $_GET,
			'post' => $_POST,
			'request' => $_REQUEST,
			'cookie' => $_COOKIE,
			'session' => $_SESSION ?? [],
			'server' => $_SERVER,
			'files' => $_FILES,
			'raw_input' => is_string($rawInput) ? $rawInput : ''
		];
	}

	/**
	 * Restore globals for code paths that still read directly from superglobals.
	 *
	 * Base3 components should normally use IRequest, but ILIAS and legacy code may
	 * still inspect $_GET, $_REQUEST or $_SERVER. Restoring the globals keeps the
	 * standalone request coherent for both styles while keeping this workaround in
	 * the ILIAS integration layer.
	 *
	 * @param array<string,mixed> $snapshot
	 */
	protected static function restoreRequestSnapshot(array $snapshot): void {
		$_GET = is_array($snapshot['get'] ?? null) ? $snapshot['get'] : [];
		$_POST = is_array($snapshot['post'] ?? null) ? $snapshot['post'] : [];
		$_REQUEST = is_array($snapshot['request'] ?? null) ? $snapshot['request'] : [];
		$_COOKIE = is_array($snapshot['cookie'] ?? null) ? $snapshot['cookie'] : [];
		$_FILES = is_array($snapshot['files'] ?? null) ? $snapshot['files'] : [];

		if(isset($snapshot['session']) && is_array($snapshot['session'])) {
			$_SESSION = $snapshot['session'];
		}

		if(isset($snapshot['server']) && is_array($snapshot['server'])) {
			$_SERVER = $snapshot['server'];
		}
	}

	/**
	 * Build an IRequest from the preserved snapshot instead of php://input.
	 *
	 * Base3\Core\Request::fromGlobals() is correct for normal requests, but it can
	 * only see the current process state. For standalone endpoints behind ILIAS we
	 * need an IRequest that carries the raw body captured before ILIAS initialized.
	 *
	 * @param array<string,mixed> $snapshot
	 */
	protected static function createRequestFromSnapshot(array $snapshot): IRequest {
		return new class($snapshot) implements IRequest {

			/** @var array<string,mixed> */
			private array $get;

			/** @var array<string,mixed> */
			private array $post;

			/** @var array<string,mixed> */
			private array $cookie;

			/** @var array<string,mixed> */
			private array $session;

			/** @var array<string,mixed> */
			private array $server;

			/** @var array<string,mixed> */
			private array $files;

			private string $rawInput;

			/**
			 * @param array<string,mixed> $snapshot
			 */
			public function __construct(array $snapshot) {
				$this->get = is_array($snapshot['get'] ?? null) ? $snapshot['get'] : [];
				$this->post = is_array($snapshot['post'] ?? null) ? $snapshot['post'] : [];
				$this->cookie = is_array($snapshot['cookie'] ?? null) ? $snapshot['cookie'] : [];
				$this->session = is_array($snapshot['session'] ?? null) ? $snapshot['session'] : [];
				$this->server = is_array($snapshot['server'] ?? null) ? $snapshot['server'] : [];
				$this->files = is_array($snapshot['files'] ?? null) ? $snapshot['files'] : [];
				$this->rawInput = is_string($snapshot['raw_input'] ?? null) ? $snapshot['raw_input'] : '';
			}

			public function get(string $key, $default = null) {
				return $this->read($this->get, $key, $default);
			}

			public function post(string $key, $default = null) {
				return $this->read($this->post, $key, $default);
			}

			public function request(string $key, $default = null) {
				return $this->read($this->allRequest(), $key, $default);
			}

			public function allRequest(): array {
				return array_replace($this->get, $this->post);
			}

			public function cookie(string $key, $default = null) {
				return $this->read($this->cookie, $key, $default);
			}

			public function session(string $key, $default = null) {
				return $this->read($this->session, $key, $default);
			}

			public function server(string $key, $default = null) {
				return $this->read($this->server, $key, $default);
			}

			public function files(string $key, $default = null) {
				return $this->read($this->files, $key, $default);
			}

			public function allGet(): array {
				return $this->get;
			}

			public function allPost(): array {
				return $this->post;
			}

			public function allCookie(): array {
				return $this->cookie;
			}

			public function allSession(): array {
				return $this->session;
			}

			public function allServer(): array {
				return $this->server;
			}

			public function allFiles(): array {
				return $this->files;
			}

			public function getJsonBody(): array {
				$raw = trim($this->rawInput);

				if($raw === '') {
					return [];
				}

				$decoded = json_decode($raw, true);

				return is_array($decoded) ? $decoded : [];
			}

			public function isCli(): bool {
				return PHP_SAPI === 'cli';
			}

			public function getContext(): string {
				if($this->isCli()) {
					return IRequest::CONTEXT_CLI;
				}

				$method = strtoupper((string)($this->server['REQUEST_METHOD'] ?? 'GET'));
				$contentType = strtolower((string)(
					$this->server['CONTENT_TYPE']
					?? $this->server['HTTP_CONTENT_TYPE']
					?? ''
				));
				$requestedWith = strtolower((string)($this->server['HTTP_X_REQUESTED_WITH'] ?? ''));

				if($requestedWith === 'xmlhttprequest') {
					return IRequest::CONTEXT_WEB_AJAX;
				}

				if($method === 'POST' && str_contains($contentType, 'multipart/form-data')) {
					return IRequest::CONTEXT_WEB_UPLOAD;
				}

				if(str_contains($contentType, 'application/json')) {
					return IRequest::CONTEXT_WEB_API;
				}

				if($method === 'POST') {
					return IRequest::CONTEXT_WEB_POST;
				}

				return IRequest::CONTEXT_WEB_GET;
			}

			/**
			 * Read direct keys and simple PHP-style nested keys such as foo[bar].
			 * This mirrors the behavior callers commonly expect from Base3 requests.
			 *
			 * @param array<string,mixed> $source
			 */
			private function read(array $source, string $key, $default = null) {
				if(array_key_exists($key, $source)) {
					return $source[$key];
				}

				if(!str_contains($key, '[')) {
					return $default;
				}

				preg_match_all('/[^\[\]]+/', $key, $matches);
				$parts = $matches[0] ?? [];

				if($parts === []) {
					return $default;
				}

				$value = $source;

				foreach($parts as $part) {
					if(!is_array($value) || !array_key_exists($part, $value)) {
						return $default;
					}

					$value = $value[$part];
				}

				return $value;
			}
		};
	}

	protected static function fillIliasContainer(Base3IliasServiceLocator $servicelocator): void {
		$services = $servicelocator->getServiceList();

		foreach ($services as $service) {
			if (isset($GLOBALS['DIC'][$service])) continue;
			$GLOBALS['DIC'][$service] = $servicelocator->get($service);
		}
	}
}
