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


namespace Base3\Base3Ilias\Api;

use Base3\CoreFoundation\Api\IBase;
use ILIAS\UI\Component\MainControls\ModeInfo;

/** Discoverable ILIAS page mode notice supplied by a BASE3 feature plugin. */
interface IModeInfoProvider extends IBase {

	/**
	 * Returns the standard ILIAS mode notice for the current authenticated request.
	 * Providers MUST return null when their mode is inactive and MUST NOT modify
	 * ILIAS session data or grant access during rendering. The ILIAS host adapter
	 * registers exactly one native layout modification provider; simultaneous
	 * non-null BASE3 mode notices are an integration error.
	 *
	 * @return ModeInfo|null Native ILIAS mode notice, or null when inactive.
	 * @throws \RuntimeException On invalid mode state or an unavailable host dependency.
	 * Side effects: None. Rendering the notice does not initiate its close action.
	 */
	public function getModeInfo(): ?ModeInfo;
}
