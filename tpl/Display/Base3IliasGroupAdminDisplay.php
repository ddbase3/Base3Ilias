<?php
$translations = is_array($this->_['translations'] ?? null) ? $this->_['translations'] : [];
$t = static function(string $key, string $fallback) use ($translations): string {
	$value = trim((string)($translations[$key] ?? ''));
	return $value !== '' ? $value : $fallback;
};
$groups = is_array($this->_['groups'] ?? null) ? $this->_['groups'] : [];
$roleOptions = is_array($this->_['roleOptions'] ?? null) ? $this->_['roleOptions'] : [];
$defaultGroup = trim((string)($this->_['defaultGroup'] ?? ''));
$message = trim((string)($this->_['message'] ?? ''));
?>
<div class="base3ilias-groups" data-base3-display="base3iliasgroupadmindisplay">
	<h3><?php echo htmlspecialchars($t('page_title', 'BASE3 groups')); ?></h3>
	<p class="groups-intro"><?php echo htmlspecialchars($t('page_description', 'Stable BASE3 group identifiers are defined by the integration. Configure only how ILIAS users are assigned to them.')); ?></p>

	<?php if ($message !== ''): ?>
		<div class="groups-message"><?php echo htmlspecialchars($message); ?></div>
	<?php endif; ?>

	<div class="groups-ajax-error" data-base3-ajax-error role="alert" hidden></div>

	<form
		method="post"
		action="<?php echo htmlspecialchars((string)$this->_['endpoint'], ENT_QUOTES); ?>"
		data-base3-ajax-form
	>
		<div class="groups-tablewrap">
			<table class="groups-table">
				<thead>
					<tr>
						<th><?php echo htmlspecialchars($t('column_group', 'BASE3 group')); ?></th>
						<th><?php echo htmlspecialchars($t('column_roles', 'ILIAS roles')); ?></th>
						<th><?php echo htmlspecialchars($t('column_users', 'ILIAS user IDs')); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($groups as $group): ?>
						<?php
						$name = (string)($group['name'] ?? '');
						$automatic = !empty($group['automatic']);
						$selectedRoleIds = array_map('intval', (array)($group['role_ids'] ?? []));
						?>
						<tr>
							<td class="groups-name-cell">
								<code><?php echo htmlspecialchars($name); ?></code>
								<?php if ((string)($group['info'] ?? '') !== ''): ?>
									<div class="groups-hint"><?php echo htmlspecialchars((string)$group['info']); ?></div>
								<?php endif; ?>
							</td>
							<?php if ($automatic): ?>
								<td colspan="2">
									<span class="groups-automatic"><?php echo htmlspecialchars($t('automatic_assignment', 'Automatic assignment by login state')); ?></span>
								</td>
							<?php else: ?>
								<td>
									<select name="base3_group_roles[<?php echo htmlspecialchars($name); ?>][]" multiple size="6" class="groups-role-select">
										<?php foreach ($roleOptions as $role): ?>
											<?php $roleId = (int)($role['id'] ?? 0); ?>
											<option value="<?php echo $roleId; ?>"<?php echo in_array($roleId, $selectedRoleIds, true) ? ' selected' : ''; ?>>
												<?php echo htmlspecialchars((string)($role['label'] ?? '')); ?> (<?php echo $roleId; ?>)
											</option>
										<?php endforeach; ?>
									</select>
									<div class="groups-hint"><?php echo htmlspecialchars($t('roles_hint', 'A match with any selected global ILIAS role assigns the group.')); ?></div>
								</td>
								<td>
									<input
										type="text"
										name="base3_group_user_ids[<?php echo htmlspecialchars($name); ?>]"
										value="<?php echo htmlspecialchars((string)($group['user_id_value'] ?? '')); ?>"
										class="groups-user-input"
										placeholder="42, 4711"
									>
									<div class="groups-hint"><?php echo htmlspecialchars($t('users_hint', 'Comma, semicolon or whitespace separated ILIAS user IDs.')); ?></div>
									<?php if (!empty($group['users'])): ?>
										<ul class="groups-users">
											<?php foreach ((array)$group['users'] as $user): ?>
												<li><?php echo (int)($user['id'] ?? 0); ?><?php echo (string)($user['label'] ?? '') !== '' ? ' · ' . htmlspecialchars((string)$user['label']) : ''; ?></li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="groups-default">
			<label for="base3_default_group"><strong><?php echo htmlspecialchars($t('default_group', 'Default group')); ?></strong></label>
			<select id="base3_default_group" name="base3_default_group">
				<option value=""><?php echo htmlspecialchars($t('default_none', 'No additional group')); ?></option>
				<?php foreach ($groups as $group): ?>
					<?php if (!empty($group['automatic'])) continue; ?>
					<?php $name = (string)($group['name'] ?? ''); ?>
					<option value="<?php echo htmlspecialchars($name); ?>"<?php echo $defaultGroup === $name ? ' selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
				<?php endforeach; ?>
			</select>
			<div class="groups-hint"><?php echo htmlspecialchars($t('default_hint', 'Applied to authenticated users only when no explicit role or user assignment matched.')); ?></div>
		</div>

		<input type="hidden" name="base3_group_action" value="save">
		<button type="submit" class="btn btn-default"><?php echo htmlspecialchars($t('save', 'Save')); ?></button>
	</form>
</div>

<style>
.base3ilias-groups {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.base3ilias-groups h3 {
	margin-top: 0;
	margin-bottom: 8px;
	font-size: 1.1em;
}

.groups-intro {
	margin: 0 0 16px 0;
	color: #555;
}

.groups-message {
	margin-bottom: 16px;
	padding: 10px 12px;
	border: 1px solid #b9d7b9;
	background: #f2faf2;
}

.groups-ajax-error {
	margin-bottom: 16px;
	padding: 10px 12px;
	border: 1px solid #d88;
	background: #fff5f5;
	color: #a33;
	border-radius: 4px;
}

.groups-tablewrap {
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
}

.groups-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
	margin-bottom: 18px;
}

.groups-table th,
.groups-table td {
	border-top: 1px solid #eee;
	padding: 10px;
	vertical-align: top;
	text-align: left;
}

.groups-table thead th {
	border-top: 0;
	border-bottom: 1px solid #ddd;
	white-space: nowrap;
}

.groups-name-cell {
	min-width: 180px;
	width: 22%;
}

.groups-name-cell code {
	font-size: 13px;
	font-weight: bold;
}

.groups-role-select,
.groups-user-input,
.groups-default select {
	width: 100%;
	max-width: 520px;
}

.groups-user-input {
	padding: 6px 8px;
	box-sizing: border-box;
}

.groups-hint {
	margin-top: 5px;
	font-size: 12px;
	color: #666;
}

.groups-automatic {
	display: inline-block;
	padding: 4px 8px;
	background: #f4f4f4;
	border: 1px solid #ddd;
	border-radius: 3px;
}

.groups-users {
	margin: 6px 0 0 18px;
	padding: 0;
	font-size: 12px;
	color: #555;
}

.groups-default {
	margin-bottom: 14px;
	max-width: 520px;
}

.groups-default label {
	display: block;
	margin-bottom: 5px;
}

.base3ilias-groups[aria-busy="true"] {
	opacity: 0.7;
}
</style>

<script>
(() => {
	const selector = '[data-base3-display="base3iliasgroupadmindisplay"]';
	const failureMessage = <?php echo json_encode($t('ajax_request_failed', 'The display could not be updated.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

	function setBusy(root, busy) {
		root.setAttribute('aria-busy', busy ? 'true' : 'false');
		root.querySelectorAll('[data-base3-ajax-form] button').forEach((button) => {
			button.disabled = busy;
		});
	}

	function setError(root, message) {
		const element = root.querySelector('[data-base3-ajax-error]');
		if (!element) return;

		element.textContent = message;
		element.hidden = message === '';
	}

	async function submit(root, form) {
		setError(root, '');
		setBusy(root, true);

		try {
			const response = await fetch(form.action, {
				method: 'POST',
				body: new FormData(form),
				credentials: 'same-origin',
				headers: {
					'Accept': 'text/html',
					'X-Requested-With': 'XMLHttpRequest'
				}
			});

			if (!response.ok) {
				throw new Error(failureMessage + ' (' + response.status + ')');
			}

			const html = await response.text();
			const responseDocument = new DOMParser().parseFromString(html, 'text/html');
			const nextRoot = responseDocument.querySelector(selector);

			if (!nextRoot) {
				throw new Error(failureMessage);
			}

			root.replaceWith(nextRoot);
			initialize(nextRoot);
		} catch (error) {
			setError(root, error instanceof Error ? error.message : failureMessage);
			setBusy(root, false);
		}
	}

	function initialize(root) {
		const form = root.querySelector('[data-base3-ajax-form]');
		if (!form || form.dataset.base3AjaxBound === 'true') return;

		form.dataset.base3AjaxBound = 'true';
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			submit(root, form);
		});
	}

	document.querySelectorAll(selector).forEach(initialize);
})();
</script>
