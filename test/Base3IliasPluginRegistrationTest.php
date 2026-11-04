<?php declare(strict_types=1);

namespace Base3Ilias\Test;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IMvcView;
use Base3\Authorization\Api\IAuthorizationAdministrationService;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Base3Ilias\Base3IliasActivityRepositoryBridge;
use Base3\Configuration\Api\IConfiguration;
use Base3\ConfigValue\Api\IConfigValueResolver;
use Base3\Database\Api\IDatabase;
use Base3\Logger\Api\ILogger;
use Base3\Migration\Api\IMigrationRunner;
use Base3\Session\Api\ISession;
use Base3\Settings\Api\ISettingsStore;
use Base3\State\Api\IStateStore;
use Base3\Translation\Api\ITranslation;
use Base3\Usermanager\Api\IUsermanager;
use Base3Ilias\Api\IBase3IliasSettings;
use Base3Ilias\Base3\Base3IliasChatbotConfigService;
use Base3Ilias\Base3\Base3IliasFileManagerHttpService;
use Base3Ilias\Base3\Base3IliasFileStorageFactory;
use Base3Ilias\Base3\Base3IliasFileUploadService;
use Base3Ilias\Base3\Base3IliasManagedFileStorageService;
use Base3Ilias\Base3\Base3IliasRichTextEditorDisplay;
use Base3Ilias\Base3IliasPlugin;
use Base3Ilias\Service\Base3IliasAudienceAdministrationAdapter;
use Base3Ilias\Service\Base3IliasAudienceEditor;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;
use Base3Ilias\Service\Base3IliasGroupConfiguration;
use ILIAS\DI\Container as IliasContainer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use ResourceFoundation\Api\IFileStorageFactory;
use ResourceFoundation\Api\IManagedFileStorageService;
use UiFoundation\Api\IAuthorizationAudienceAdministrationService;
use UiFoundation\Api\IRichTextEditorDisplay;

require_once __DIR__ . '/../classes/Base3IliasActivityRepositoryBridge.php';

final class Base3IliasPluginRegistrationTest extends TestCase {

	public function testRegistersTheCanonicalBase3AndIliasIntegrationServices(): void {
		$entries = $this->registrations();
		foreach ([
			IDatabase::class,
			IMigrationRunner::class,
			IConfiguration::class,
			IStateStore::class,
			ISettingsStore::class,
			IFileStorageFactory::class,
			IManagedFileStorageService::class,
			Base3IliasManagedFileStorageService::class,
			Base3IliasFileUploadService::class,
			Base3IliasFileManagerHttpService::class,
			Base3IliasChatbotConfigService::class,
			ISession::class,
			IAccesscontrol::class,
			IUsermanager::class,
			IAuthorizationService::class,
			IAuthorizationAdministrationService::class,
			IAuthorizationAudienceAdministrationService::class,
			Base3IliasFeatureConfiguration::class,
			Base3IliasGroupConfiguration::class,
			Base3IliasAudienceEditor::class,
			IMvcView::class,
			IAssetResolver::class,
			IRichTextEditorDisplay::class,
			IBase3IliasSettings::class,
			IConfigValueResolver::class,
			'workers', 'middlewares', 'authentications'
		] as $name) {
			self::assertArrayHasKey($name, $entries, $name);
			self::assertInstanceOf(\Closure::class, $entries[$name][0], $name);
		}
		self::assertGreaterThan(35, count($entries));
	}

	public function testRegistersCorrectAliasesInsteadOfCompetingServices(): void {
		$entries = $this->registrations();
		foreach ([
			'database' => IDatabase::class,
			'logger' => ILogger::class,
			'configuration' => IConfiguration::class,
			'session' => ISession::class,
			'accesscontrol' => IAccesscontrol::class,
			'usermanager' => IUsermanager::class,
			'view' => IMvcView::class
		] as $alias => $target) {
			self::assertSame($target, $entries[$alias][0]);
			self::assertSame(IContainer::ALIAS, $entries[$alias][1]);
		}
		self::assertSame(Base3IliasManagedFileStorageService::class, $entries[IManagedFileStorageService::class][0]);
		self::assertSame(IContainer::ALIAS, $entries[IManagedFileStorageService::class][1]);
	}

	public function testProtectsFoundationDefaultBindingsWithNoOverwriteFlag(): void {
		$entries = $this->registrations();
		foreach ([
			ILogger::class, ISettingsStore::class, IFileStorageFactory::class,
			ITranslation::class, IBase3IliasSettings::class,
			IConfigValueResolver::class, 'authentications'
		] as $service) {
			self::assertSame(
				IContainer::SHARED | IContainer::NOOVERWRITE,
				$entries[$service][1],
				$service
			);
		}
	}

	public function testRegistersNativeHostContainerAndItsPluginInstance(): void {
		$ilias = new \stdClass();
		$entries = $this->registrations($ilias);
		self::assertSame($ilias, $entries[\Pimple\Container::class][0]);
		self::assertSame(IContainer::SHARED, $entries[\Pimple\Container::class][1]);
		self::assertInstanceOf(Base3IliasPlugin::class, $entries['base3iliasplugin'][0]);
		self::assertSame(IContainer::SHARED, $entries['base3iliasplugin'][1]);
	}

	public function testWorkerDefinitionKeepsOneNativeBase3IliasWorker(): void {
		$entries = $this->registrations();
		$workerDefinitions = $entries['workers'][0]($this->createMock(IContainer::class));
		self::assertSame(['Base3Ilias'], array_keys($workerDefinitions));
		self::assertInstanceOf(\Closure::class, $workerDefinitions['Base3Ilias']);
	}

	public function testFactoryForNativeFileStorageUsesTheInjectedIliasContainer(): void {
		$entries = $this->registrations();
		$ilias = $this->createMock(IliasContainer::class);
		$container = $this->createMock(IContainer::class);
		$container->expects(self::once())->method('get')->with(IliasContainer::class)->willReturn($ilias);
		$factory = $entries[IFileStorageFactory::class][0]($container);
		self::assertInstanceOf(Base3IliasFileStorageFactory::class, $factory);
	}

	public function testNativeActivitiesRepositoryNeverGetsAnIndependentImplementation(): void {
		$entries = $this->registrations();
		$repositoryClass = \ILIAS\Component\Activities\Repository::class;
		if (interface_exists($repositoryClass)) {
			self::assertArrayHasKey($repositoryClass, $entries);
			self::assertInstanceOf(\Closure::class, $entries[$repositoryClass][0]);
		}
		else {
			self::assertArrayNotHasKey($repositoryClass, $entries);
		}
	}

	/** @return array<string, array{0:mixed,1:int}> */
	private function registrations(?object $ilias = null): array {
		$entries = [];
		$container = $this->createMock(IContainer::class);
		$container->method('set')->willReturnCallback(
			static function(string $name, mixed $definition, int $flags = 0) use (&$entries, $container): IContainer {
				self::assertArrayNotHasKey($name, $entries, 'A service must have a single owner.');
				$entries[$name] = [$definition, $flags];
				return $container;
			}
		);

		$hadNativeContainer = array_key_exists('DIC', $GLOBALS);
		$previousNativeContainer = $GLOBALS['DIC'] ?? null;
		$repositoryResolver = new ReflectionProperty(Base3IliasActivityRepositoryBridge::class, 'resolver');
		$previousResolver = $repositoryResolver->getValue();
		try {
			$GLOBALS['DIC'] = $ilias ?? new \stdClass();
			if (interface_exists(\ILIAS\Component\Activities\Repository::class)) {
				Base3IliasActivityRepositoryBridge::publishResolver(static fn() => null);
			}
			(new Base3IliasPlugin($container))->init();
		}
		finally {
			$repositoryResolver->setValue($previousResolver);
			if ($hadNativeContainer) $GLOBALS['DIC'] = $previousNativeContainer;
			else unset($GLOBALS['DIC']);
		}
		return $entries;
	}
}
