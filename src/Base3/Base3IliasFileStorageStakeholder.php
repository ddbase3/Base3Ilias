<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use ILIAS\ResourceStorage\Identification\ResourceIdentification;
use ILIAS\ResourceStorage\Stakeholder\AbstractResourceStakeholder;

final class Base3IliasFileStorageStakeholder extends AbstractResourceStakeholder {

	public function getId(): string {
		return 'base3ilias_file_storage';
	}

	public function getConsumerNameForPresentation(): string {
		return 'BASE3 ILIAS File Storage';
	}

	public function isResourceInUse(ResourceIdentification $identification): bool {
		return true;
	}

	public function getOwnerOfResource(ResourceIdentification $identification): int {
		return $this->default_owner;
	}

	public function getOwnerOfNewResources(): int {
		return $this->default_owner;
	}
}
