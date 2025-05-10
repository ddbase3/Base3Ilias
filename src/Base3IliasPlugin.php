<?php declare(strict_types=1);

namespace Base3Ilias;

use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Database\Api\IDatabase;
use Pimple\Container;

class Base3IliasPlugin implements IPlugin
{
    public function __construct(private readonly IContainer $container) {}

    // Implementation of IBase

    public function getName(): string {
        return strtolower($this->getClassName());
    }

    // Implementation of IPlugin

    public function init(): void
    {
        global $DIC;
        $this->container
            ->set($this->getName(), $this, IContainer::SHARED)
            ->set(Container::class, $DIC, IContainer::SHARED)
            ->set(IDatabase::class, new Base3IliasDatabase, IContainer::SHARED);
    }

    // Private methods

    private function getClassName(): string {
        return (new \ReflectionClass($this))->getShortName();
    }
}