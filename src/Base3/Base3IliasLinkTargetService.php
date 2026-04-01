<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Configuration\Api\IConfiguration;
use Base3\LinkTarget\Api\ILinkTargetService;

/**
 * Query-based link target service for Base3Ilias.
 *
 * Example:
 * - target: ['name' => 'chatservice', 'out' => 'php']
 * - params: ['baseprompt' => 1]
 * - default result:
 *   index.php?baseClass=ilUIPluginRouterGUI&cmdClass=ilBase3IliasAdapterAjaxGUI&cmd=dispatch&name=chatservice&out=php&baseprompt=1
 *
 * If "out" is omitted, "php" is used.
 */
class Base3IliasLinkTargetService implements ILinkTargetService {

	private string $endpoint;

	public function __construct(
		private readonly IConfiguration $configuration
	) {}

	/**
	 * Builds a Base3Ilias query link.
	 *
	 * @param array<string,mixed> $target
	 * @param array<string,mixed> $params
	 * @return string
	 */
	public function getLink(array $target, array $params = []): string {
		$query = [
			'name' => (string) ($target['name'] ?? ''),
			'out' => (string) ($target['out'] ?? 'php')
		];

		foreach ($params as $key => $value) {
			$query[$key] = $value;
		}

		$queryString = http_build_query($query);
		$endpoint = $this->getEndpoint();

		if ($queryString === '') return $endpoint;

		$separator = str_contains($endpoint, '?') ? '&' : '?';

		return $endpoint . $separator . $queryString;
	}

	private function getEndpoint(): string {
		if (isset($this->endpoint)) return $this->endpoint;

		$config = $this->configuration->get('base');
		$this->endpoint = isset($config['endpoint']) && trim((string) $config['endpoint']) !== ''
			? (string) $config['endpoint']
			: $this->getDefaultEndpoint();

		return $this->endpoint;
	}

	private function getDefaultEndpoint(): string {
		$query = http_build_query([
			'baseClass' => 'ilUIPluginRouterGUI',
			'cmdClass' => 'ilBase3IliasAdapterAjaxGUI',
			'cmd' => 'dispatch'
		]);

		return 'index.php?' . $query;
	}
}
