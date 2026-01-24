<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Language\Api\ILanguage;

/**
 * Class Base3IliasLanguage
 *
 * Maps Base3 language service to the ILIAS user language preference.
 *
 * Primary goal:
 * - Provide the logged-in user's chosen language for UI rendering.
 *
 * Notes:
 * - setLanguage() is intentionally a no-op for now (would require persistence).
 * - getLanguages() returns best-effort list (fallback: current language only).
 */
final class Base3IliasLanguage implements ILanguage {

	public function __construct() {
	}

	public function getLanguage(): string {
		global $DIC;

		if (isset($DIC) && method_exists($DIC, 'user') && $DIC->user()) {
			$lang = (string) $DIC->user()->getLanguage();
			if ($lang !== '') {
				return $lang;
			}
		}

		return 'en';
	}

	public function setLanguage(string $language) {
		// Intentionally not implemented:
		// Persisting user language is out of scope for this adapter right now.
		// If you ever want request-scoped switching, this could call:
		// $DIC->language()->setCurrentLanguage($language);
	}

	public function getLanguages(): array {
		// Out of scope for now. Keep it simple and safe.
		return [$this->getLanguage()];
	}
}
