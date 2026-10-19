<?php declare(strict_types=1);

namespace Base3Ilias\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Session\Api\ISession;
use Base3\Usermanager\Permission;
use Base3\Usermanager\Api\IUsermanager;
use Base3Ilias\Service\Base3IliasAudienceEditor;
use Base3Ilias\Service\Base3IliasGroupConfiguration;
use InvalidArgumentException;

/** Administers native-host-owned BASE3 group DTOs and discoverable membership criteria. */
final class Base3IliasGroupAdminDisplay implements IDisplay {

	private const SESSION_CSRF = 'authorization.groups.csrf';

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly Base3IliasGroupConfiguration $groups,
		private readonly Base3IliasAudienceEditor $audiences,
		private readonly IAuthorizationService $authorization,
		private readonly IUsermanager $usermanager,
		private readonly ISession $session,
		private readonly ILinkTargetService $links
	) {}

	public static function getName(): string { return 'base3iliasgroupadmindisplay'; }

	public function setData($data) {}

	public function getHelp(): string {
		return 'Manage BASE3 groups and their ILIAS user, role and plugin-defined criterion memberships. Native RBAC is not changed.';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if ($out !== 'html') throw new InvalidArgumentException('Group administration renders HTML only.');
		if (!$this->authorization->decide(new AuthorizationRequest(
			$this->authorization->getDefaultAuthority(), Permission::for('system', 'admin')
		))->isAllowed() || !$this->isDeveloper()) {
			http_response_code(403);
			return '<p role="alert">Access denied.</p>';
		}
		$message = '';
		$action = (string)$this->request->post('base3_group_action', '');
		if ($action !== '') {
			$this->assertCsrf();
			switch ($action) {
				case 'save':
					$this->saveRules();
					$message = 'Group memberships saved.';
					break;
				case 'create':
					$this->groups->create((string)$this->request->post('base3_group_name', ''), (string)$this->request->post('base3_group_description', ''));
					$message = 'BASE3 group created.';
					break;
				case 'remove':
					$this->groups->remove((string)$this->request->post('base3_group_name', ''));
					$message = 'BASE3 group removed.';
					break;
				default:
					throw new InvalidArgumentException('Invalid BASE3 group action.');
			}
		}
		$entries = [];
		foreach ($this->groups->getEntries() as $entry) {
			$entries[] = [
				'name' => (string)$entry['group']->name,
				'description' => (string)$entry['group']->info,
				'editable' => $entry['editable'],
				'source' => $entry['source'],
				'rows' => $this->audiences->describe($entry['configuredCriteria']),
				'codeCriteria' => $entry['codeCriteria'],
				'expression' => $entry['criteria']
			];
		}
		$settings = $this->groups->get();
		$this->view->setPath(dirname(__DIR__, 2));
		$this->view->loadBricks('Display');
		$this->view->setTemplate('Display/Base3IliasGroupAdminDisplay.php');
		$this->view->assign('translations', array_merge($this->view->getBricks('base3ilias_group_admin_display') ?? [], $this->view->getBricks('base3ilias_audience_editor') ?? []));
		$this->view->assign('entries', $entries);
		$this->view->assign('roleOptions', $this->audiences->getRoleOptions());
		$this->view->assign('criterionOptions', $this->audiences->getCriteria());
		$this->view->assign('defaultGroup', (string)($settings['default_group'] ?? ''));
		$this->view->assign('message', $message);
		$this->view->assign('endpoint', $this->links->getLink(['name' => self::getName(), 'out' => 'html']));
		$this->view->assign('csrf', $this->csrfToken());
		return $this->view->loadTemplate();
	}

	private function saveRules(): void {
		$submitted = $this->request->post('base3_group_rules', []);
		if (!is_array($submitted)) throw new InvalidArgumentException('Invalid group rules.');
		$known = [];
		foreach ($this->groups->getEntries() as $entry) if ($entry['editable']) $known[(string)$entry['group']->name] = true;
		foreach ($submitted as $name => $rows) {
			if (!isset($known[$name]) || !is_array($rows)) throw new InvalidArgumentException('Unknown or locked group in form.');
		}
		$rules = [];
		foreach ($known as $name => $ignored) $rules[$name] = $this->audiences->parse($submitted[$name] ?? [], null, true);
		$this->groups->replaceAll($rules, trim((string)$this->request->post('base3_default_group', '')));
	}

	/** BASE3 membership is authoritative for development; ILIAS administrator is checked above. */
	private function isDeveloper(): bool {
		foreach ($this->usermanager->getGroups() as $group) {
			if ((string)$group->name === 'development') return true;
		}
		return false;
	}

	private function assertCsrf(): void {
		$expected = $this->session->get(self::SESSION_CSRF);
		$provided = $this->request->post('base3_group_csrf', '');
		if (!is_string($expected) || !is_string($provided) || !hash_equals($expected, $provided)) {
			http_response_code(403);
			throw new InvalidArgumentException('Invalid group administration CSRF token.');
		}
	}

	private function csrfToken(): string {
		if (!$this->session->started()) throw new \RuntimeException('An active ILIAS session is required.');
		$token = $this->session->get(self::SESSION_CSRF);
		if (!is_string($token) || strlen($token) !== 64) {
			$token = bin2hex(random_bytes(32));
			$this->session->set(self::SESSION_CSRF, $token);
		}
		return $token;
	}
}
