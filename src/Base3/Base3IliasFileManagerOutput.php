<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\IOutput;
use Base3\Api\IRequest;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Permission;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3Ilias\Api\IBase3IliasSettings;

/**
 * Exposes the existing ILIAS-managed FileManager HTTP service through normal
 * BASE3 output dispatch. Its owner must be declared by the project settings.
 */
final class Base3IliasFileManagerOutput implements IOutput {

	public function __construct(
		private readonly IRequest $request,
		private readonly IUsermanager $usermanager,
		private readonly IAuthorizationService $authorization,
		private readonly IBase3IliasSettings $settings,
		private readonly Base3IliasFileManagerHttpService $fileManagerHttpService
	) {}

	public static function getName(): string {
		return 'base3iliasfilemanageroutput';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if (!$this->authorization->decide(new AuthorizationRequest($this->authorization->getDefaultAuthority(), Permission::for('system', 'admin')))->isAllowed()) {
			return $this->errorResponse(403, 'FileManager access denied.');
		}

		$group = $this->queryString('fm_group');
		$name = $this->queryString('fm_name');
		if ($group === '' || $name === '' || !$this->isConfiguredOwner($group, $name)) {
			return $this->errorResponse(404, 'FileManager configuration not found.');
		}

		$userId = (int)($this->usermanager->getUser()?->id ?? 0);
		if ($userId <= 0) {
			return $this->errorResponse(403, 'FileManager access denied.');
		}

		$this->fileManagerHttpService->handle(
			$this->queryString('fm_action'),
			$group,
			$name,
			Base3IliasFileStorage::MODE_COLLECTION,
			$userId,
			Base3IliasFileManagerHttpService::DEFAULT_MAX_FILE_SIZE
		);

		return '';
	}

	private function queryString(string $key): string {
		$value = $this->request->get($key, '');
		return is_scalar($value) ? trim((string)$value) : '';
	}

	private function isConfiguredOwner(string $group, string $name): bool {
		foreach ($this->settings->getAdministrationConfig() as $tab) {
			if (!is_array($tab)) {
				continue;
			}

			foreach ($tab['displays'] ?? [] as $display) {
				if (!is_array($display)) {
					continue;
				}

				$data = $display['data'] ?? null;
				if (!is_array($data) || empty($data['resources']['enabled'])) {
					continue;
				}

				if (($data['group'] ?? null) === $group && ($data['name'] ?? null) === $name) {
					return true;
				}
			}
		}

		return false;
	}

	private function errorResponse(int $status, string $message): string {
		http_response_code($status);
		if (!headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
			header('Cache-Control: no-store');
		}

		return json_encode(['message' => $message], JSON_THROW_ON_ERROR);
	}
}
