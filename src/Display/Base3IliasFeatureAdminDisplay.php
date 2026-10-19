<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Api\IFeature;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Session\Api\ISession;
use Base3\Usermanager\Permission;
use Base3\Usermanager\Api\IUsermanager;
use Base3Ilias\Service\Base3IliasAudienceEditor;
use Base3Ilias\Service\Base3IliasFeatureConfiguration;
use Base3Ilias\Service\Base3IliasGroupConfiguration;
use InvalidArgumentException;

/** One UI for group, user and criterion feature audiences, without native RBAC duplication. */
final class Base3IliasFeatureAdminDisplay implements IDisplay {

	private const CSRF_KEY = 'authorization.features.csrf';

	public function __construct(
		private readonly IAuthorizationService $authorization,
		private readonly IUsermanager $usermanager,
		private readonly IClassMap $classMap,
		private readonly Base3IliasFeatureConfiguration $features,
		private readonly Base3IliasGroupConfiguration $groups,
		private readonly Base3IliasAudienceEditor $audiences,
		private readonly IRequest $request,
		private readonly ISession $session,
		private readonly IMvcView $view,
		private readonly ILinkTargetService $links
	) {}

	public static function getName(): string { return 'base3iliasfeatureadmindisplay'; }

	public function setData($data) {}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if ($out !== 'html') throw new InvalidArgumentException('Feature administration renders HTML only.');
		if (!$this->authorization->decide(new AuthorizationRequest(
			$this->authorization->getDefaultAuthority(), Permission::for('system', 'admin')
		))->isAllowed() || !$this->isDeveloper()) {
			http_response_code(403);
			return '<p role="alert">Access denied.</p>';
		}
		$catalog = $this->catalog();
		$groups = array_map(static fn($group): string => (string)$group->name, $this->groups->getGroups());
		$message = '';
		if ($this->request->post('base3_feature_action', '') === 'save') {
			$this->assertCsrf();
			$submitted = $this->request->post('base3_feature_rules', []);
			if (!is_array($submitted)) throw new InvalidArgumentException('Invalid feature audience form.');
			foreach ($submitted as $name => $rows) {
				if (!isset($catalog[$name]) || !is_array($rows)) throw new InvalidArgumentException('Unknown feature or invalid rule rows.');
			}
			$changes = [];
			foreach ($catalog as $name => $feature) {
				$changes[$name] = $this->audiences->parse($submitted[$name] ?? [], $groups, true);
			}
			$this->features->replaceAll($changes, $groups);
			$message = 'Feature audiences saved.';
		}
		$rows = [];
		foreach ($catalog as $feature) {
			$permissions = [];
			foreach ($feature->getPermissions() as $permission) $permissions[] = (string)$permission->scope . '/' . (string)$permission->permission;
			$rows[] = [
				'name' => $feature::getName(),
				'label' => $feature->getLabel(),
				'description' => $feature->getDescription(),
				'permissions' => $permissions,
				'rules' => $this->audiences->describe($this->features->getAudience($feature))
			];
		}
		$this->view->setPath(dirname(__DIR__, 2));
		$this->view->loadBricks('Display');
		$this->view->setTemplate('Display/Base3IliasFeatureAdminDisplay.php');
		$this->view->assign('translations', array_merge($this->view->getBricks('base3ilias_feature_admin_display') ?? [], $this->view->getBricks('base3ilias_audience_editor') ?? []));
		$this->view->assign('rows', $rows);
		$this->view->assign('groups', $groups);
		$this->view->assign('criterionOptions', $this->audiences->getCriteria());
		$this->view->assign('roleOptions', $this->audiences->getRoleOptions());
		$this->view->assign('csrf', $this->csrfToken());
		$this->view->assign('message', $message);
		$this->view->assign('endpoint', $this->links->getLink(['name' => self::getName(), 'out' => 'html']));
		return $this->view->loadTemplate();
	}

	/** @return array<string,IFeature> */
	private function catalog(): array {
		$features = [];
		foreach ($this->classMap->getInstancesByInterface(IFeature::class) as $feature) {
			if (!$feature instanceof IFeature) continue;
			$name = $feature::getName();
			if (isset($features[$name])) throw new InvalidArgumentException('Duplicate feature identifier.');
			$features[$name] = $feature;
		}
		ksort($features);
		return $features;
	}

	/** BASE3 membership is authoritative for development; ILIAS administrator is checked above. */
	private function isDeveloper(): bool {
		foreach ($this->usermanager->getGroups() as $group) {
			if ((string)$group->name === 'development') return true;
		}
		return false;
	}

	private function assertCsrf(): void {
		$expected = $this->session->get(self::CSRF_KEY);
		$provided = $this->request->post('base3_feature_csrf', '');
		if (!is_string($expected) || !is_string($provided) || !hash_equals($expected, $provided)) {
			http_response_code(403);
			throw new InvalidArgumentException('Invalid feature administration CSRF token.');
		}
	}

	private function csrfToken(): string {
		if (!$this->session->started()) throw new \RuntimeException('An active ILIAS session is required.');
		$token = $this->session->get(self::CSRF_KEY);
		if (!is_string($token) || strlen($token) !== 64) {
			$token = bin2hex(random_bytes(32));
			$this->session->set(self::CSRF_KEY, $token);
		}
		return $token;
	}
}
