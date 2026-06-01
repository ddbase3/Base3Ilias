<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Language\Api\ILanguage;
use Base3\Translation\Api\ITranslation;

/**
 * Class Base3IliasTranslation
 *
 * Loads translations from Base3 component language files.
 */
class Base3IliasTranslation implements ITranslation {

	/**
	 * @var array<int, string>
	 */
	protected array $componentPaths;

	/**
	 * @var array<string, array<string, array<string, string>>>
	 */
	protected array $translations = [];

	/**
	 * @param array<int, string>|null $componentPaths
	 */
	public function __construct(
		protected readonly ILanguage $language,
		?array $componentPaths = null
	) {
		$this->componentPaths = $componentPaths ?? $this->resolveComponentPaths();
	}

	public function translate(string $set, string $section, string $key, string $fallback = '', array $replacements = []): string {
		foreach($this->getLanguageFallbacks() as $language) {
			$translations = $this->loadTranslations($set, $language);

			if(!isset($translations[$section]) || !array_key_exists($key, $translations[$section])) {
				continue;
			}

			$value = trim((string) $translations[$section][$key]);

			if($value === '') {
				continue;
			}

			return $this->applyReplacements($value, $replacements);
		}

		return $this->applyReplacements($fallback !== '' ? $fallback : $key, $replacements);
	}

	/**
	 * @return array<int, string>
	 */
	protected function resolveComponentPaths(): array {
		if(!defined('DIR_BASE3')) {
			return [dirname(__DIR__, 2)];
		}

		$paths = [];

		$basePath = rtrim(DIR_BASE3, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Base3Ilias';
		if(is_dir($basePath)) {
			$paths[] = $basePath;
		}

		$componentPaths = glob(rtrim(DIR_BASE3, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*');

		if(!is_array($componentPaths)) {
			return $paths;
		}

		sort($componentPaths);

		foreach($componentPaths as $componentPath) {
			if(!is_dir($componentPath)) {
				continue;
			}

			if(basename($componentPath) === 'Base3Ilias') {
				continue;
			}

			if(!is_dir($componentPath . DIRECTORY_SEPARATOR . 'lang')) {
				continue;
			}

			$paths[] = $componentPath;
		}

		return array_values(array_unique($paths));
	}

	/**
	 * @return array<int, string>
	 */
	protected function getLanguageFallbacks(): array {
		$language = trim($this->language->getLanguage());

		if($language === '') {
			$language = 'en';
		}

		return array_values(array_unique([$language, 'en']));
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	protected function loadTranslations(string $set, string $language): array {
		$cacheKey = $set . ':' . $language;

		if(array_key_exists($cacheKey, $this->translations)) {
			return $this->translations[$cacheKey];
		}

		$translations = [];

		foreach($this->componentPaths as $componentPath) {
			$filename = rtrim($componentPath, DIRECTORY_SEPARATOR)
				. DIRECTORY_SEPARATOR . 'lang'
				. DIRECTORY_SEPARATOR . $set
				. DIRECTORY_SEPARATOR . $language . '.ini';

			if(!is_file($filename)) {
				continue;
			}

			$componentTranslations = parse_ini_file($filename, true, INI_SCANNER_RAW);

			if(!is_array($componentTranslations)) {
				continue;
			}

			$translations = $this->mergeTranslations($translations, $componentTranslations);
		}

		$this->translations[$cacheKey] = $translations;
		return $translations;
	}

	/**
	 * @param array<string, mixed> $base
	 * @param array<string, mixed> $override
	 * @return array<string, array<string, string>>
	 */
	protected function mergeTranslations(array $base, array $override): array {
		foreach($override as $section => $values) {
			if(!is_array($values)) {
				continue;
			}

			if(!isset($base[$section]) || !is_array($base[$section])) {
				$base[$section] = [];
			}

			foreach($values as $key => $value) {
				$base[$section][(string) $key] = (string) $value;
			}
		}

		return $base;
	}

	/**
	 * @param array<string, scalar|null> $replacements
	 */
	protected function applyReplacements(string $text, array $replacements): string {
		if(empty($replacements)) {
			return $text;
		}

		$prepared = [];

		foreach($replacements as $key => $value) {
			$value = (string) $value;
			$key = (string) $key;

			$prepared[$key] = $value;
			$prepared['{' . $key . '}'] = $value;
		}

		return strtr($text, $prepared);
	}
}
