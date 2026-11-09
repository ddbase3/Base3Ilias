<?php declare(strict_types=1);

namespace Base3Ilias\Api;

use Base3\Api\IBase;
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
