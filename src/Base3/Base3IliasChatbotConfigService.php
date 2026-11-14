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


namespace Base3\Base3Ilias\Base3;

use Base3\CoreFoundation\Api\IDisplay;
use Base3\CoreFoundation\Configuration\Api\IConfiguration;
use RuntimeException;

/**
 * Provides the shared ILIAS host integration for ChatbotConfigDisplay.
 *
 * RepositoryObject, UIHook and CoPage hosts use the same configuration and
 * FileManager collection path. Hosts only provide their SettingsStore identity,
 * labels and the authorized ILIAS FileManager command URL.
 */
final class Base3IliasChatbotConfigService {

	public function __construct(
		private readonly Base3IliasFileManagerHttpService $fileManagerHttpService,
		private readonly IConfiguration $configuration
	) {}

	public function configureDisplay(
		IDisplay $display,
		string $group,
		string $name,
		string $title,
		string $description,
		string $submitLabel,
		string $fileManagerBaseUrl
	): void {
		$display->setData($this->getDisplayData(
			$group,
			$name,
			$title,
			$description,
			$submitLabel,
			$fileManagerBaseUrl
		));
	}

	/**
	 * Returns the same host-provided resource configuration for a configured
	 * BASE3 administration display without requiring a display instance.
	 *
	 * @return array<string, mixed>
	 */
	public function getDisplayData(
		string $group,
		string $name,
		string $title,
		string $description,
		string $submitLabel,
		string $fileManagerBaseUrl
	): array {
		$fileManagerBaseUrl = $this->resolveAbsoluteUrl($fileManagerBaseUrl);

		return [
			'group' => $group,
			'name' => $name,
			'title' => $title,
			'description' => $description,
			'submit_label' => $submitLabel,
			'mode' => 'standalone',
			'save_mode' => 'ajax',
			'resources' => [
				'enabled' => true,
				'endpoints' => Base3IliasFileManagerHttpService::buildEndpoints($fileManagerBaseUrl),
				'max_file_size' => Base3IliasFileManagerHttpService::DEFAULT_MAX_FILE_SIZE,
				'accept' => 'application/pdf,.pdf',
			]
		];
	}

	public function handleFileManager(
		string $action,
		string $group,
		string $name,
		int $userId
	): void {
		$this->fileManagerHttpService->handle(
			$action,
			$group,
			$name,
			Base3IliasFileStorage::MODE_COLLECTION,
			$userId,
			Base3IliasFileManagerHttpService::DEFAULT_MAX_FILE_SIZE
		);
	}

	private function resolveAbsoluteUrl(string $url): string {
		$url = trim($url);
		if($url === '') {
			throw new RuntimeException('FileManager base URL is empty.');
		}

		if(preg_match('#^https?://#i', $url) === 1) {
			return $url;
		}

		$base = $this->configuration->get('base');
		$baseUrl = isset($base['url']) && is_scalar($base['url'])
			? trim((string)$base['url'])
			: '';

		if($baseUrl === '') {
			throw new RuntimeException('BASE3 ILIAS base URL is not configured.');
		}

		if(str_starts_with($url, '/')) {
			$parts = parse_url($baseUrl);
			$scheme = isset($parts['scheme']) ? trim((string)$parts['scheme']) : '';
			$host = isset($parts['host']) ? trim((string)$parts['host']) : '';
			$port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';

			if($scheme === '' || $host === '') {
				throw new RuntimeException('BASE3 ILIAS base URL is invalid.');
			}

			return $scheme . '://' . $host . $port . $url;
		}

		return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
	}
}
