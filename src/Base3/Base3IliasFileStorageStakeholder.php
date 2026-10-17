<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use ILIAS\DI\Container;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;
use ILIAS\ResourceStorage\Stakeholder\AbstractResourceStakeholder;

final class Base3IliasFileStorageStakeholder extends AbstractResourceStakeholder {

	protected int $default_owner;
	protected int $current_user;

	/**
	 * ILIAS 9 requires a constructor on the concrete stakeholder; its abstract
	 * base does not initialize owner state. Use the ILIAS 10+ owner semantics.
	 */
	public function __construct() {
		global $DIC;

		$ownerId = $DIC instanceof Container && $DIC->isDependencyAvailable('user')
			? (int)$DIC->user()->getId()
			: (defined('SYSTEM_USER_ID') ? (int)SYSTEM_USER_ID : 6);

		$this->default_owner = $this->current_user = $ownerId;
	}

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
