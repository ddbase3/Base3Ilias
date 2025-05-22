<?php declare(strict_types=1);

namespace Base3Ilias;

use Base3\Api\IAssetResolver;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Configuration\Api\IConfiguration;
use Base3\Database\Api\IDatabase;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Accesscontrol\Selected\SelectedAccesscontrol;
use Base3\Usermanager\Api\IUsermanager;
use Pimple\Container;

class Base3IliasPlugin implements IPlugin {
    
	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase

	public function getName(): string {
		return strtolower($this->getClassName());
	}

	// Implementation of IPlugin

	public function init(): void {
		$this->container
			->set($this->getName(), $this, IContainer::SHARED)
			->set(Container::class, $GLOBALS['DIC'], IContainer::SHARED)
			->set(IDatabase::class, new Base3IliasDatabase, IContainer::SHARED)
			->set('configuration', new Base3IliasConfiguration($this->container->get(IDatabase::class)), IContainer::SHARED)
			->set(IConfiguration::class, 'configuration', IContainer::ALIAS)
                        ->set('authentications', [ fn() => new Base3IliasAuth($this->container->get('ilAuthSession')) ])
                        ->set('accesscontrol', new SelectedAccesscontrol($this->container->get('authentications')), IContainer::SHARED)
			->set(IAccesscontrol::class, 'accesscontrol', IContainer::ALIAS)
			->set('usermanager', fn() => new Base3IliasUsermanager, IContainer::SHARED)
			->set(IUsermanager::class, 'usermanager', IContainer::ALIAS)
			->set(IAssetResolver::class, fn() => new Base3IliasAssetResolver, IContainer::SHARED);
	}

	// Private methods

	private function getClassName(): string {
		return (new \ReflectionClass($this))->getShortName();
	}
}
