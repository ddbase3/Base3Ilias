<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\IOutput;
use Base3\Api\IClassMap;
use UiFoundation\Api\ISettingsTab;
use Chatbot\SettingsTab\ChatbotconfigSettingsTab;
use Base3\Api\IRequest;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Permission;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;

/**
 * Exposes the existing ILIAS-managed FileManager HTTP service through normal
 * BASE3 output dispatch. Its owner must be declared by the project settings.
 */
final class Base3IliasFileManagerOutput implements IOutput {

	public function __construct(
		private readonly IRequest $request,
		private readonly IUsermanager $usermanager,
		private readonly IAuthorizationService $authorization,
		private readonly IClassMap $classmap,
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
		$tab = $this->classmap->getInstanceByInterfaceName(
			ISettingsTab::class, ChatbotconfigSettingsTab::getName()
		);
		if (!$tab instanceof ISettingsTab || !$tab->isVisible()) {
			return false;
		}

		$data = $tab->getDisplayData();
		return is_array($data)
			&& ($data['resources']['enabled'] ?? false) === true
			&& ($data['group'] ?? null) === $group
			&& ($data['name'] ?? null) === $name;
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
