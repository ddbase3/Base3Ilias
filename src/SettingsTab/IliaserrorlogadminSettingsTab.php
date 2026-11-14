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


namespace Base3\Base3Ilias\SettingsTab;

use Base3\UiFoundation\Settings\AbstractAdministrationSettingsTab;

/** Plugin-owned ILIAS administration entry. */
final class IliaserrorlogadminSettingsTab extends AbstractAdministrationSettingsTab {

	public function getParentTabName(): ?string {
		return 'iliassettingstab';
	}

	public function getDisplayName(): ?string {
		return 'iliaserrorlogadmindisplay';
	}

	public function getLabel(): string {
		return $this->translation->translate('Administration', 'administration', 'base3_admin_subtab_iliaserrorlogadmindisplay');
	}

	public function getDescription(): string {
		return $this->getLabel();
	}

	public function getOrder(): int {
		return 40;
	}
}
