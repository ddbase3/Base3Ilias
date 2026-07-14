<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Language\Api\ILanguage;
use ilObject;
use ilObjLanguage;

/**
 * Class Base3IliasLanguage
 *
 * Maps the BASE3 language service to the ILIAS language configuration.
 *
 * The current language is taken from the logged-in ILIAS user.
 * Available languages are derived from the languages installed in ILIAS.
 *
 * setLanguage() is intentionally a no-op because persisting or changing
 * the user's language is currently outside the scope of this adapter.
 */
final class Base3IliasLanguage implements ILanguage {

	public function __construct() {
	}

	public function getLanguage(): string {
		global $DIC;

		if (isset($DIC) && method_exists($DIC, 'user') && $DIC->user()) {
			$language = (string) $DIC->user()->getLanguage();
			if ($language !== '') {
				return $language;
			}
		}

		return 'en';
	}

	public function setLanguage(string $language) {
		// Intentionally not implemented.
		// Persisting or changing the ILIAS user language is outside the
		// current responsibility of this adapter.
	}

	public function getLanguages(): array {
		$languages = [];

		foreach (ilObject::_getObjectsByType('lng') as $languageData) {
			$objectId = (int)($languageData['obj_id'] ?? 0);
			if ($objectId <= 0) {
				continue;
			}

			$languageObject = new ilObjLanguage($objectId, false);
			if (!$languageObject->isInstalled()) {
				continue;
			}

			$language = trim((string) $languageObject->getKey());
			if ($language === '') {
				continue;
			}

			$languages[$language] = $language;
		}

		if ($languages === []) {
			return [$this->getLanguage()];
		}

		ksort($languages, SORT_STRING);

		return array_values($languages);
	}
}
