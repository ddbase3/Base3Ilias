<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Base3Ilias for BASE3 Framework.
 *
 * Base3Ilias integrates BASE3 with the ILIAS learning platform.
 * It provides native identity, services, assets and lifecycle adapters.
 * Application modules use the shared BASE3 Foundation contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0-or-later
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 ***********************************************************************/


namespace Base3\Base3Ilias;

use Base3\CoreFoundation\Api\IAssetResolver;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationAdministrationService;
use Base3\Core\Usermanager\UserCriterionEvaluator;
use Base3\Base3Ilias\Authorization\Base3IliasAuthorizationService;
use Base3\Base3Ilias\Authorization\Base3IliasAuthorizationAdministrationService;
use Base3\Base3Ilias\Service\Base3IliasGroupConfiguration;
use Base3\Base3Ilias\Service\Base3IliasFeatureConfiguration;
use Base3\Base3Ilias\Service\Base3IliasAudienceEditor;
use Base3\Base3Ilias\Service\Base3IliasAudienceAdministrationAdapter;
use Base3\UiFoundation\Api\IAuthorizationAudienceAdministrationService;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Api\IMvcView;
use Base3\CoreFoundation\Api\IPlugin;
use Base3\CoreFoundation\Api\ISystemService;
use Base3\CoreFoundation\Configuration\Api\IConfiguration;
use Base3\CoreFoundation\ConfigValue\Api\IConfigValueResolver;
use Base3\Core\ConfigValue\Resolver\ConfigValueResolver;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\Core\Event\EventManager;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Migration\Api\IMigrationRunner;
use Base3\Core\Migration\Database\DatabaseMigrationRunner;
use Base3\CoreFoundation\Accesscontrol\Api\IAccesscontrol;
use Base3\Core\Accesscontrol\Selected\SelectedAccesscontrol;
use Base3\CoreFoundation\Language\Api\ILanguage;
use Base3\Core\Middleware\Session\SessionMiddleware;
use Base3\Core\Middleware\Accesscontrol\AccesscontrolMiddleware;
use Base3\CoreFoundation\Session\Api\ISession;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\Core\Settings\Database\DatabaseSettingsStore;
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\Core\Worker\DelegateWorker;
use Base3\Base3Ilias\Base3\Base3IliasAssetResolver;
use Base3\Base3Ilias\Base3\Base3IliasAuth;
use Base3\Base3Ilias\Base3\Base3IliasConfiguration;
use Base3\Base3Ilias\Base3\Base3IliasFileStorageFactory;
use Base3\Base3Ilias\Base3\Base3IliasManagedFileStorageService;
use Base3\Base3Ilias\Base3\Base3IliasMvcView;
use Base3\Base3Ilias\Base3\Base3IliasFileUploadService;
use Base3\Base3Ilias\Base3\Base3IliasFileManagerHttpService;
use Base3\Base3Ilias\Base3\Base3IliasChatbotConfigService;
use Base3\Base3Ilias\Base3\Base3IliasDatabase;
use Base3\Base3Ilias\Base3\Base3IliasLanguage;
use Base3\Base3Ilias\Base3\Base3IliasLogger;
use Base3\Base3Ilias\Base3\Base3IliasRichTextEditorDisplay;
use Base3\Base3Ilias\Base3\Base3IliasSession;
use Base3\Base3Ilias\Base3\Base3IliasStateStore;
use Base3\Base3Ilias\Base3\Base3IliasTranslation;
use Base3\Base3Ilias\Base3\Base3IliasUsermanager;
use Base3\Base3Ilias\Base3IliasActivityRepositoryBridge;
use Base3\Base3Ilias\Base3IliasModuleRegistry;
use ILIAS\DI\Container as IliasContainer;
use Pimple\Container;
use Base3\ResourceFoundation\Api\IFileStorageFactory;
use Base3\ResourceFoundation\Api\IManagedFileStorageService;
use ReflectionClass;
use Base3\UiFoundation\Api\IRichTextEditorDisplay;

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
			->set(IStateStore::class, fn($c) => new Base3IliasStateStore($c->get(IDatabase::class)), IContainer::SHARED)
			->set(ISettingsStore::class, fn($c) => new DatabaseSettingsStore($c->get(IDatabase::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IEventManager::class, fn() => new EventManager(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(
				IFileStorageFactory::class,
				fn($c) => new Base3IliasFileStorageFactory($c->get(IliasContainer::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				Base3IliasManagedFileStorageService::class,
				fn($c) => new Base3IliasManagedFileStorageService(
					$c->get(ISettingsStore::class),
					$c->get(IFileStorageFactory::class),
					$c->get(IliasContainer::class),
					$c->get(IEventManager::class)
				),
				IContainer::SHARED
			)
			->set(IManagedFileStorageService::class, Base3IliasManagedFileStorageService::class, IContainer::ALIAS)
			->set(
				Base3IliasFileUploadService::class,
				fn($c) => new Base3IliasFileUploadService($c->get(Base3IliasManagedFileStorageService::class)),
				IContainer::SHARED
			)
			->set(
				Base3IliasFileManagerHttpService::class,
				fn($c) => new Base3IliasFileManagerHttpService(
					$c->get(Base3IliasManagedFileStorageService::class),
					$c->get(Base3IliasFileUploadService::class),
					$c->get(IliasContainer::class)
				),
				IContainer::SHARED
			)
			->set(
				Base3IliasChatbotConfigService::class,
				fn($c) => new Base3IliasChatbotConfigService(
					$c->get(Base3IliasFileManagerHttpService::class),
					$c->get(IConfiguration::class)
				),
				IContainer::SHARED
			)
			->set('authentications', fn($c) => [ new Base3IliasAuth($c->get('ilAuthSession')) ], IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(ISession::class, fn() => new Base3IliasSession(), IContainer::SHARED)
			->set('session', ISession::class, IContainer::ALIAS)
			->set(IAccesscontrol::class, fn($c) => new SelectedAccesscontrol($c->get('authentications')), IContainer::SHARED)
			->set('accesscontrol', IAccesscontrol::class, IContainer::ALIAS)
			->set('middlewares', fn($c) => [
				new SessionMiddleware($c->get(ISession::class)),
				new AccesscontrolMiddleware($c->get(IAccesscontrol::class))
			])
			->set(Base3IliasFeatureConfiguration::class, fn($c) => new Base3IliasFeatureConfiguration($c->get(ISettingsStore::class), $c->get(UserCriterionEvaluator::class), $c->get(IClassMap::class)), IContainer::SHARED)
			->set(UserCriterionEvaluator::class, fn($c) => new UserCriterionEvaluator($c->get(IClassMap::class)), IContainer::SHARED)
			->set(Base3IliasAudienceEditor::class, fn($c) => new Base3IliasAudienceEditor($c->get(IClassMap::class), $c->get(UserCriterionEvaluator::class), $c->get(Container::class)->rbac()->review()), IContainer::SHARED)
			->set(Base3IliasGroupConfiguration::class, fn($c) => new Base3IliasGroupConfiguration($c->get(ISettingsStore::class), $c->get(IClassMap::class), $c->get(UserCriterionEvaluator::class), $c->get(Base3IliasFeatureConfiguration::class)), IContainer::SHARED)
			->set(IAuthorizationAudienceAdministrationService::class, fn($c) => new Base3IliasAudienceAdministrationAdapter(
				$c->get(Base3IliasGroupConfiguration::class),
				$c->get(Base3IliasFeatureConfiguration::class),
				$c->get(Base3IliasAudienceEditor::class),
				$c->get(IClassMap::class),
				$c->get(IUsermanager::class)
			), IContainer::SHARED)
			->set(IUsermanager::class, fn($c) => new Base3IliasUsermanager(
				$c->get(IAccesscontrol::class),
				$c->get(Container::class)->rbac()->review(),
				$c->get(UserCriterionEvaluator::class),
				$c->get(Base3IliasGroupConfiguration::class),
				$c->get(Container::class)->database()
			), IContainer::SHARED)
			->set(IAuthorizationService::class, fn($c) => new Base3IliasAuthorizationService(
				$c->get(IAccesscontrol::class),
				$c->get(IUsermanager::class),
				$c->get(IClassMap::class),
				$c->get(Base3IliasFeatureConfiguration::class),
				$c->get(UserCriterionEvaluator::class),
				$c->get(Container::class)->rbac()->system(),
				$c->get(ILogger::class)
			), IContainer::SHARED)
			->set(IAuthorizationAdministrationService::class, fn($c) => new Base3IliasAuthorizationAdministrationService(
				$c->get(IAuthorizationService::class),
				$c->get(Container::class)->rbac()->review(),
				$c->get(Container::class)->rbac()->admin(),
				$c->get(Container::class)->database()
			), IContainer::SHARED)
			->set('usermanager', IUsermanager::class, IContainer::ALIAS)
			->set(ILanguage::class, fn() => new Base3IliasLanguage(), IContainer::SHARED)
			->set(ITranslation::class, fn($c) => new Base3IliasTranslation(
				$c->get(ILanguage::class),
				$c->get(Base3IliasModuleRegistry::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IMvcView::class, fn($c) => new Base3IliasMvcView(
				$c->get(ILanguage::class),
				$c->get(Base3IliasModuleRegistry::class)
			))
			->set('view', IMvcView::class, IContainer::ALIAS)
			->set(
				IRichTextEditorDisplay::class,
				fn($c) => new Base3IliasRichTextEditorDisplay($c->get(IMvcView::class)),
				IContainer::SHARED
			)
			->set(IAssetResolver::class, fn($c) => new Base3IliasAssetResolver(
				$c->get(ISystemService::class),
				$c->get(Base3IliasModuleRegistry::class)
			), IContainer::SHARED)
			->set(IConfigValueResolver::class, fn($c) => new ConfigValueResolver($c->get(IClassMap::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set('workers', fn($c) => [
				'Base3Ilias' => fn() => new DelegateWorker($c->get(IClassMap::class), $c->get(IConfiguration::class))
			]);

		if (interface_exists(\ILIAS\Component\Activities\Repository::class)) {
			if (!Base3IliasActivityRepositoryBridge::hasResolver()) {
				throw new \RuntimeException(
					'ILIAS 11 Activities Repository is available, but Base3Ilias did not receive the component repository resolver.'
				);
			}

			$this->container->set(
				\ILIAS\Component\Activities\Repository::class,
				fn() => Base3IliasActivityRepositoryBridge::current()->getRepository(),
				IContainer::SHARED
			);
		}
	}

	// Private methods

	private function getClassName(): string {
		return (new ReflectionClass($this))->getShortName();
	}
}
