<?php declare(strict_types=1);

namespace Base3Ilias;

use Base3\Api\IAssetResolver;
use Base3\Api\IContainer;
use Base3\Api\IMvcView;
use Base3\Api\IPlugin;
use Base3\Configuration\Api\IConfiguration;
use Base3\Core\MvcView;
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Accesscontrol\Selected\SelectedAccesscontrol;
use Base3\Middleware\Session\SessionMiddleware;
use Base3\Middleware\Accesscontrol\AccesscontrolMiddleware;
use Base3\Session\Api\ISession;
use Base3\Session\NoSession\NoSession;
use Base3\State\Api\IStateStore;
use Base3\State\Database\DatabaseStateStore;
use Base3\Usermanager\Api\IUsermanager;
use Base3Ilias\Base3\Base3IliasAssetResolver;
use Base3Ilias\Base3\Base3IliasAuth;
use Base3Ilias\Base3\Base3IliasConfiguration;
use Base3Ilias\Base3\Base3IliasDatabase;
use Base3Ilias\Base3\Base3IliasLogger;
use Base3Ilias\Base3\Base3IliasUsermanager;
use Pimple\Container;
use ReflectionClass;

class Base3IliasPlugin implements IPlugin {
    
	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase

	public static function getName(): string {
		$fullClass = static::class;
		$parts = explode('\\', $fullClass);
		return strtolower(end($parts));
	}

	// Implementation of IPlugin

	public function init(): void {
		$this->container
			->set($this->getName(), $this, IContainer::SHARED)
			->set(Container::class, $GLOBALS['DIC'], IContainer::SHARED)
			->set(IDatabase::class, fn() => new Base3IliasDatabase, IContainer::SHARED)
			->set('database', IDatabase::class, IContainer::ALIAS)
			->set('logger', fn() => new Base3IliasLogger, IContainer::SHARED)
			->set(ILogger::class, 'logger', IContainer::ALIAS)
			->set('configuration', fn($c) => new Base3IliasConfiguration($c->get(IDatabase::class)), IContainer::SHARED)
			->set(IConfiguration::class, 'configuration', IContainer::ALIAS)
			->set(IStateStore::class, fn($c) => new DatabaseStateStore($c->get(IDatabase::class)), IContainer::SHARED)
			->set('authentications', fn($c) => [ new Base3IliasAuth($this->container->get('ilAuthSession')) ])
			->set('session', fn($c) => new NoSession($c->get(IConfiguration::class)), IContainer::SHARED)
			->set(ISession::class, 'session', IContainer::ALIAS)
			->set('accesscontrol', new SelectedAccesscontrol($this->container->get('authentications')), IContainer::SHARED)
			->set(IAccesscontrol::class, 'accesscontrol', IContainer::ALIAS)
			->set('middlewares', fn($c) => [
				new SessionMiddleware($c->get(ISession::class)),
				new AccesscontrolMiddleware($c->get(IAccesscontrol::class))
			])
			->set('usermanager', fn() => new Base3IliasUsermanager, IContainer::SHARED)
			->set(IUsermanager::class, 'usermanager', IContainer::ALIAS)
			->set('view', fn() => new MvcView)
			->set(IMvcView::class, 'view', IContainer::ALIAS)
			->set(IAssetResolver::class, fn() => new Base3IliasAssetResolver, IContainer::SHARED);
	}

	// Private methods

	private function getClassName(): string {
		return (new ReflectionClass($this))->getShortName();
	}
}
