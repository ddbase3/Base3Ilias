<?php declare(strict_types=1);

namespace Base3Ilias;

use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IMvcView;
use Base3\Api\IPlugin;
use Base3\Configuration\Api\IConfiguration;
use Base3\ConfigValue\Api\IConfigValueResolver;
use Base3\ConfigValue\Resolver\ConfigValueResolver;
use Base3\Core\MvcView;
use Base3\Database\Api\IDatabase;
use Base3\Event\Api\IEventManager;
use Base3\Event\EventManager;
use Base3\Logger\Api\ILogger;
use Base3\Migration\Api\IMigrationRunner;
use Base3\Migration\Database\DatabaseMigrationRunner;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Accesscontrol\Selected\SelectedAccesscontrol;
use Base3\Language\Api\ILanguage;
use Base3\Middleware\Session\SessionMiddleware;
use Base3\Middleware\Accesscontrol\AccesscontrolMiddleware;
use Base3\Session\Api\ISession;
use Base3\State\Api\IStateStore;
use Base3\State\Database\DatabaseStateStore;
use Base3\Translation\Api\ITranslation;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Worker\DelegateWorker;
use Base3Ilias\Api\IBase3IliasSettings;
use Base3Ilias\Base3\Base3IliasAssetResolver;
use Base3Ilias\Base3\Base3IliasAuth;
use Base3Ilias\Base3\Base3IliasConfiguration;
use Base3Ilias\Base3\Base3IliasDatabase;
use Base3Ilias\Base3\Base3IliasLanguage;
use Base3Ilias\Base3\Base3IliasLogger;
use Base3Ilias\Base3\Base3IliasSession;
use Base3Ilias\Base3\Base3IliasSettings;
use Base3Ilias\Base3\Base3IliasTranslation;
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
                        ->set(IDatabase::class, fn() => new Base3IliasDatabase(), IContainer::SHARED)
                        ->set('database', IDatabase::class, IContainer::ALIAS)
                        ->set(IMigrationRunner::class, fn($c) => new DatabaseMigrationRunner($c->get(IClassMap::class), $c->get(IDatabase::class)), IContainer::SHARED)
                        ->set(ILogger::class, fn() => new Base3IliasLogger(), IContainer::SHARED | IContainer::NOOVERWRITE)
                        ->set('logger', ILogger::class, IContainer::ALIAS)
                        ->set(IConfiguration::class, fn($c) => new Base3IliasConfiguration($c->get(IDatabase::class)), IContainer::SHARED)
                        ->set('configuration', IConfiguration::class, IContainer::ALIAS)
                        ->set(IStateStore::class, fn($c) => new DatabaseStateStore($c->get(IDatabase::class)), IContainer::SHARED)
                        ->set('authentications', fn($c) => [ new Base3IliasAuth($c->get('ilAuthSession')) ])
                        ->set(ISession::class, fn() => new Base3IliasSession(), IContainer::SHARED)
                        ->set('session', ISession::class, IContainer::ALIAS)
                        ->set(IAccesscontrol::class, fn($c) => new SelectedAccesscontrol($c->get('authentications')), IContainer::SHARED)
                        ->set('accesscontrol', IAccesscontrol::class, IContainer::ALIAS)
                        ->set('middlewares', fn($c) => [
                                new SessionMiddleware($c->get(ISession::class)),
                                new AccesscontrolMiddleware($c->get(IAccesscontrol::class))
                        ])
                        ->set(IUsermanager::class, fn() => new Base3IliasUsermanager(), IContainer::SHARED)
                        ->set('usermanager', IUsermanager::class, IContainer::ALIAS)
                        ->set(ILanguage::class, fn() => new Base3IliasLanguage(), IContainer::SHARED)
                        ->set(ITranslation::class, fn($c) => new Base3IliasTranslation($c->get(ILanguage::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
                        ->set(IMvcView::class, fn($c) => new MvcView($c->get(ILanguage::class)))
                        ->set('view', IMvcView::class, IContainer::ALIAS)
                        ->set(IEventManager::class, fn() => new EventManager(), IContainer::SHARED | IContainer::NOOVERWRITE)
                        ->set(IAssetResolver::class, fn() => new Base3IliasAssetResolver(), IContainer::SHARED)
                        ->set(IBase3IliasSettings::class, fn() => new Base3IliasSettings(), IContainer::SHARED | IContainer::NOOVERWRITE)
                        ->set(IConfigValueResolver::class, fn($c) => new ConfigValueResolver($c->get(IClassMap::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
                        ->set('workers', fn($c) => [
                                'Base3Ilias' => fn() => new DelegateWorker($c->get(IClassMap::class), $c->get(IConfiguration::class))
                        ]);
        }

        // Private methods

        private function getClassName(): string {
                return (new ReflectionClass($this))->getShortName();
        }
}
