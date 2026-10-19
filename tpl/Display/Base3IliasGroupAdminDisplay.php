<?php
$entries = (array)($this->_['entries'] ?? []);
$criterionOptions = (array)($this->_['criterionOptions'] ?? []);
$availableGroups = array_column($entries, 'name');
$message = (string)($this->_['message'] ?? '');
$csrf = (string)($this->_['csrf'] ?? '');
$endpoint = (string)($this->_['endpoint'] ?? '');
$defaultGroup = (string)($this->_['defaultGroup'] ?? '');
$translations = (array)($this->_['translations'] ?? []);
$t = static fn(string $key, string $fallback): string => (string)($translations[$key] ?? $fallback);
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="base3-authorization-editor" data-base3-display="base3iliasgroupadmindisplay">
	<h3><?php echo $esc($t('page_title', 'BASE3 groups')); ?></h3>
	<p><?php echo $esc($t('page_description', 'Code-defined and host-managed BASE3 groups can be assigned through native ILIAS users, roles and discoverable criteria.')); ?></p>
	<?php if ($message !== ''): ?><p role="status" tabindex="-1" data-base3-status><?php echo $esc($message); ?></p><?php endif; ?>
	<p role="alert" data-base3-error hidden></p>

	<form method="post" action="<?php echo $esc($endpoint); ?>" data-base3-ajax-form>
		<?php foreach ($entries as $entry): ?>
			<fieldset>
				<legend><code><?php echo $esc((string)$entry['name']); ?></code><?php echo $entry['source'] === 'managed' ? ' (' . $esc($t('custom', 'custom')) . ')' : ''; ?></legend>
				<p class="base3-group-summary"><?php echo $esc((string)$entry['description']); ?></p>
				<?php if ($entry['codeCriteria'] !== []): ?>
					<p class="base3-audience-hint">Code-defined membership (always effective, cannot be removed here):
						<code><?php echo $esc(json_encode($entry['codeCriteria'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)); ?></code>
					</p>
				<?php endif; ?>
				<?php if ($entry['editable']): ?>
					<?php
					$editorPrefix = 'base3_group_rules[' . $entry['name'] . ']';
					$editorRows = $entry['rows'];
					$allowRoles = true;
				$roleListId = 'base3-ilias-group-role-options';
					$allowGroups = false;
					require __DIR__ . '/AuthorizationAudienceEditor.php';
					?>
				<?php else: ?>
					<p class="base3-group-summary"><?php echo $esc($t('locked_hint', 'Membership is defined by its PHP implementation and cannot be changed here.')); ?></p>
					<code><?php echo $esc(json_encode($entry['expression'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)); ?></code>
				<?php endif; ?>
			</fieldset>
		<?php endforeach; ?>
		<div class="base3-section">
			<label for="base3-default-group"><?php echo $esc($t('default_group', 'Fallback group for authenticated users without an explicit match')); ?></label>
			<select id="base3-default-group" name="base3_default_group" class="form-control">
				<option value=""><?php echo $esc($t('default_none', 'None')); ?></option>
				<?php foreach ($entries as $entry): ?>
					<?php if (!$entry['editable'] || in_array($entry['name'], ['development', 'administration'], true)) continue; ?>
					<option value="<?php echo $esc($entry['name']); ?>"<?php echo $defaultGroup === $entry['name'] ? ' selected' : ''; ?>><?php echo $esc($entry['name']); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="base3-audience-hint"><?php echo $esc($t('default_hint', 'Development and administration require explicit membership criteria.')); ?></p>
		</div>
		<input type="hidden" name="base3_group_action" value="save">
		<input type="hidden" name="base3_group_csrf" value="<?php echo $esc($csrf); ?>">
		<button type="submit" class="btn btn-primary"><?php echo $esc($t('save', 'Save memberships')); ?></button>
	</form>

	<div class="base3-section">
		<h4><?php echo $esc($t('create_title', 'Create BASE3 group')); ?></h4>
		<form method="post" action="<?php echo $esc($endpoint); ?>" data-base3-ajax-form class="base3-action-row">
			<label>Technical name <input type="text" name="base3_group_name" pattern="[a-z][a-z0-9_-]{1,63}" maxlength="64" placeholder="my_project_group" required></label>
			<label>Description <input type="text" name="base3_group_description" maxlength="250" required></label>
			<input type="hidden" name="base3_group_action" value="create">
			<input type="hidden" name="base3_group_csrf" value="<?php echo $esc($csrf); ?>">
			<button type="submit" class="btn btn-default"><?php echo $esc($t('create', 'Create group')); ?></button>
		</form>
	</div>

	<?php if (array_filter($entries, static fn(array $entry): bool => $entry['source'] === 'managed')): ?>
		<div class="base3-section">
			<h4><?php echo $esc($t('remove_title', 'Remove custom group')); ?></h4>
			<p><?php echo $esc($t('remove_hint', 'Groups referenced by a feature or fallback cannot be removed.')); ?></p>
			<?php foreach ($entries as $entry): ?>
				<?php if ($entry['source'] !== 'managed') continue; ?>
				<form method="post" action="<?php echo $esc($endpoint); ?>" data-base3-ajax-form class="base3-action-row">
					<strong><?php echo $esc($entry['name']); ?></strong>
					<input type="hidden" name="base3_group_name" value="<?php echo $esc($entry['name']); ?>">
					<input type="hidden" name="base3_group_action" value="remove">
					<input type="hidden" name="base3_group_csrf" value="<?php echo $esc($csrf); ?>">
					<button type="submit" class="btn btn-default" data-confirm-remove><?php echo $esc($t('remove', 'Remove')); ?></button>
				</form>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<datalist id="base3-ilias-group-role-options">
		<?php foreach ((array)($this->_['roleOptions'] ?? []) as $role): ?>
			<option value="<?php echo (int)$role['id']; ?>" label="<?php echo $esc((string)$role['label']); ?>"></option>
		<?php endforeach; ?>
	</datalist>
	<p class="base3-audience-hint"><?php echo $esc($t('roles_hint', 'Global role suggestions are offered; existing local ILIAS role IDs may also be entered.')); ?></p>
</section>
<?php require __DIR__ . '/AuthorizationAudienceStyles.php'; ?>
<?php require __DIR__ . '/AuthorizationAudienceScript.php'; ?>
<script>
(() => {
	const selector = '[data-base3-display="base3iliasgroupadmindisplay"]';
	async function bind(root) {
		if (root.dataset.bound === 'true') return;
		root.dataset.bound = 'true';
		window.BASE3AudienceEditor.bindAll(root);
		for (const form of root.querySelectorAll('[data-base3-ajax-form]')) {
			form.addEventListener('submit', async (event) => {
				event.preventDefault();
				if (event.submitter?.matches('[data-confirm-remove]') && !confirm('Remove this BASE3 group?')) return;
				const button = event.submitter;
				const error = root.querySelector('[data-base3-error]');
				if (button) button.disabled = true;
				error.hidden = true;
				try {
					const response = await fetch(form.action, {method:'POST',body:new FormData(form),credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'}});
					if (!response.ok) throw new Error('Group update failed (HTTP ' + response.status + ').');
					const replacement = new DOMParser().parseFromString(await response.text(), 'text/html').querySelector(selector);
					if (!replacement) throw new Error('The updated group administration response is invalid.');
					root.replaceWith(replacement);
					bind(replacement);
					const status = replacement.querySelector('[data-base3-status]');
					if (status) status.focus();
				} catch (e) {
					error.textContent = e instanceof Error ? e.message : 'Group update failed.';
					error.hidden = false;
					if (button) button.disabled = false;
				}
			});
		}
	}
	document.querySelectorAll(selector).forEach(bind);
})();
</script>
