<?php declare(strict_types=1);

namespace Base3Ilias\SettingsTab;

use UiFoundation\Settings\AbstractSettingsTab;

/** ILIAS-owned first-level settings group. */
final class IliasSettingsTab extends AbstractSettingsTab {

	public function getLabel(): string {
		return $this->translation->translate('Administration', 'administration', 'base3_admin_tab_ilias');
	}

	public function getDescription(): string {
		return $this->getLabel();
	}

	public function getOrder(): int {
		return 5;
	}
}
