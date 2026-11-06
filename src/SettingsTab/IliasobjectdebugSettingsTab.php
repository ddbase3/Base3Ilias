<?php declare(strict_types=1);

namespace Base3Ilias\SettingsTab;

use UiFoundation\Settings\AbstractAdministrationSettingsTab;

/** Plugin-owned ILIAS administration entry. */
final class IliasobjectdebugSettingsTab extends AbstractAdministrationSettingsTab {

	public function getParentTabName(): ?string {
		return 'iliassettingstab';
	}

	public function getDisplayName(): ?string {
		return 'iliasobjectdebugdisplay';
	}

	public function getLabel(): string {
		return $this->translation->translate('Administration', 'administration', 'base3_admin_subtab_iliasobjectdebugdisplay');
	}

	public function getDescription(): string {
		return $this->getLabel();
	}

	public function getOrder(): int {
		return 90;
	}
}
