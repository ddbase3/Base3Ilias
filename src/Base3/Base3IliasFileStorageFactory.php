<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use ILIAS\DI\Container;
use ResourceFoundation\Api\IFileStorage;
use ResourceFoundation\Api\IFileStorageFactory;

final class Base3IliasFileStorageFactory implements IFileStorageFactory {

	public function __construct(
		private readonly Container $container
	) {}

	public function openStorage(string $id, string $mode): IFileStorage {
		return new Base3IliasFileStorage(
			$id,
			$mode,
			$this->container->resourceStorage(),
			new Base3IliasFileStorageStakeholder()
		);
	}
}
