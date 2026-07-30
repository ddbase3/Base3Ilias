<?php
$translations = is_array($this->_['translations'] ?? null) ? $this->_['translations'] : [];
$t = static function(string $key, string $fallback) use ($translations): string {
	$value = trim((string)($translations[$key] ?? ''));
	return $value !== '' ? $value : $fallback;
};
?>
<div class="base3ilias-object" data-base3-display="iliasobjectdebugdisplay">
	<h3><?php echo htmlspecialchars($t('page_title', 'ILIAS object debug')); ?></h3>

	<div class="object-meta">
		<div><strong><?php echo htmlspecialchars($t('source', 'Source:')); ?></strong> <span class="mono">ilObject + ilTree</span></div>
		<div><strong><?php echo htmlspecialchars($t('generated', 'Generated:')); ?></strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['generatedAt']); ?></span></div>
	</div>

	<form
		class="object-actions"
		method="post"
		action="<?php echo htmlspecialchars((string)$this->_['endpoint'], ENT_QUOTES); ?>"
		data-base3-ajax-form
	>
		<label class="object-ref">
			<?php echo htmlspecialchars($t('target_ref_id_input', 'Target ref_id:')); ?>
			<input
				type="number"
				name="<?php echo htmlspecialchars((string)$this->_['targetParamName'], ENT_QUOTES); ?>"
				value="<?php echo (int)$this->_['targetRefId']; ?>"
				min="1"
			>
		</label>

		<button type="submit"><?php echo htmlspecialchars($t('check_now', 'Check')); ?></button>

		<div class="object-note">
			<?php echo htmlspecialchars($t('uses_own_url_parameter', 'Uses its own request parameter:')); ?>
			<span class="mono"><?php echo htmlspecialchars((string)$this->_['targetParamName']); ?></span>.
			<span class="mono">ref_id</span> <?php echo htmlspecialchars($t('ref_id_unchanged', 'is not changed.')); ?>
		</div>
	</form>

	<div class="object-ajax-error" data-base3-ajax-error role="alert" hidden></div>

	<div class="object-section">
		<div class="object-section-head">
			<h4><?php echo htmlspecialchars($t('object_title', 'Object')); ?></h4>
			<div class="object-description"><?php echo htmlspecialchars($t('object_description', 'Basic data for the target object.')); ?></div>
		</div>

		<div class="object-tablewrap">
			<table class="object-table">
				<thead>
					<tr>
						<th><?php echo htmlspecialchars($t('column_label', 'Label')); ?></th>
						<th><?php echo htmlspecialchars($t('column_key', 'Key')); ?></th>
						<th><?php echo htmlspecialchars($t('column_value', 'Value')); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ((array)$this->_['objectRows'] as $row): ?>
						<tr>
							<td class="object-cell-label"><?php echo htmlspecialchars((string)$row['label']); ?></td>
							<td class="object-cell-mono"><?php echo htmlspecialchars((string)$row['key']); ?></td>
							<td class="object-cell-value">
								<?php if ((string)$row['value'] === ''): ?>
									<span class="object-muted">–</span>
								<?php else: ?>
									<?php echo htmlspecialchars((string)$row['value']); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="object-section">
		<div class="object-section-head">
			<h4><?php echo htmlspecialchars($t('repository_path_title', 'Repository path')); ?></h4>
			<div class="object-description"><?php echo htmlspecialchars($t('repository_path_description', 'Path from the repository root to the target object.')); ?></div>
		</div>

		<?php if (empty($this->_['pathRows'])): ?>
			<div class="object-empty"><?php echo htmlspecialchars($t('no_path_or_target', 'No path is available or no target ref_id was specified.')); ?></div>
		<?php else: ?>
			<div class="object-tablewrap">
				<table class="object-table">
					<thead>
						<tr>
							<th><?php echo htmlspecialchars($t('column_depth', 'Depth')); ?></th>
							<th><?php echo htmlspecialchars($t('column_ref_id', 'Ref ID')); ?></th>
							<th><?php echo htmlspecialchars($t('column_obj_id', 'Obj ID')); ?></th>
							<th><?php echo htmlspecialchars($t('column_type', 'Type')); ?></th>
							<th><?php echo htmlspecialchars($t('column_title', 'Title')); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$this->_['pathRows'] as $row): ?>
							<tr>
								<td class="object-cell-mono"><?php echo htmlspecialchars((string)$row['depth']); ?></td>
								<td class="object-cell-mono"><?php echo htmlspecialchars((string)$row['ref_id']); ?></td>
								<td class="object-cell-mono"><?php echo htmlspecialchars((string)$row['obj_id']); ?></td>
								<td class="object-cell-mono"><?php echo htmlspecialchars((string)$row['type']); ?></td>
								<td class="object-cell-value">
									<?php if ((string)$row['title'] === ''): ?>
										<span class="object-muted">–</span>
									<?php else: ?>
										<?php echo htmlspecialchars((string)$row['title']); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="object-section">
		<div class="object-section-head">
			<h4><?php echo htmlspecialchars($t('direct_children_title', 'Direct children')); ?></h4>
			<div class="object-description"><?php echo htmlspecialchars($t('direct_children_description', 'Direct children of the target object, limited to')); ?> <?php echo (int)$this->_['maxChildren']; ?> <?php echo htmlspecialchars($t('entries_noun', 'entries.')); ?></div>
		</div>

		<?php if (empty($this->_['childRows'])): ?>
			<div class="object-empty"><?php echo htmlspecialchars($t('no_children_or_target', 'No direct children were found or no target ref_id was specified.')); ?></div>
		<?php else: ?>
			<div class="object-tablewrap">
				<table class="object-table">
					<thead>
						<tr>
							<th><?php echo htmlspecialchars($t('column_ref_id', 'Ref ID')); ?></th>
							<th><?php echo htmlspecialchars($t('column_obj_id', 'Obj ID')); ?></th>
							<th><?php echo htmlspecialchars($t('column_type', 'Type')); ?></th>
							<th><?php echo htmlspecialchars($t('column_title', 'Title')); ?></th>
							<th><?php echo htmlspecialchars($t('column_description', 'Description')); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$this->_['childRows'] as $row): ?>
							<tr>
								<td class="object-cell-mono"><?php echo htmlspecialchars((string)$row['ref_id']); ?></td>
								<td class="object-cell-mono"><?php echo htmlspecialchars((string)$row['obj_id']); ?></td>
								<td class="object-cell-mono"><?php echo htmlspecialchars((string)$row['type']); ?></td>
								<td class="object-cell-value">
									<?php if ((string)$row['title'] === ''): ?>
										<span class="object-muted">–</span>
									<?php else: ?>
										<?php echo htmlspecialchars((string)$row['title']); ?>
									<?php endif; ?>
								</td>
								<td class="object-cell-value">
									<?php if ((string)$row['description'] === ''): ?>
										<span class="object-muted">–</span>
									<?php else: ?>
										<?php echo htmlspecialchars((string)$row['description']); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>

<style>
.base3ilias-object {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.base3ilias-object h3 {
	margin-top: 0;
	margin-bottom: 12px;
	font-size: 1.1em;
}

.object-meta {
	margin-bottom: 16px;
	font-size: 13px;
	color: #555;
	display: flex;
	gap: 18px;
	flex-wrap: wrap;
}

.mono {
	font-family: Consolas, monospace;
}

.object-actions {
	border: 1px solid #ddd;
	background: #f8f8f8;
	border-radius: 4px;
	padding: 12px;
	margin-bottom: 16px;
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}

.object-ref {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	color: #555;
}

.object-ref input {
	width: 120px;
	padding: 6px 10px;
	border: 1px solid #ccc;
	border-radius: 4px;
	background: #fff;
	color: #333;
}

.object-actions button {
	padding: 8px 16px;
	border: 1px solid #ccc;
	background: #f0f0f0;
	color: #333;
	border-radius: 4px;
	cursor: pointer;
	font-size: 14px;
	transition: background 0.2s, border-color 0.2s;
}

.object-actions button:hover {
	background: #e6e6e6;
	border-color: #bbb;
}

.object-note {
	font-size: 13px;
	color: #666;
}

.object-section {
	border-top: 1px solid #eee;
	padding-top: 14px;
	margin-top: 14px;
}

.object-section:first-of-type {
	border-top: 0;
	padding-top: 0;
	margin-top: 0;
}

.object-section-head {
	margin-bottom: 10px;
}

.object-section h4 {
	margin: 0 0 4px 0;
	font-size: 1em;
	color: #333;
}

.object-description {
	font-size: 13px;
	color: #666;
}

.object-tablewrap {
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
}

.object-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.object-table th,
.object-table td {
	border-top: 1px solid #eee;
	padding: 8px 10px;
	vertical-align: top;
	text-align: left;
}

.object-table thead th {
	border-top: 0;
	border-bottom: 1px solid #ddd;
	font-weight: bold;
	white-space: nowrap;
}

.object-cell-label {
	white-space: nowrap;
}

.object-cell-mono {
	font-family: Consolas, monospace;
	white-space: nowrap;
	color: #444;
}

.object-cell-value {
	font-family: Consolas, monospace;
	/* white-space: pre-wrap; */
	word-break: break-word;
}

.object-muted,
.object-empty {
	color: #777;
	font-style: italic;
}

.object-empty {
	border-top: 1px solid #eee;
	padding: 8px 10px;
	font-size: 13px;
}

.object-ajax-error {
	margin-bottom: 16px;
	padding: 10px 12px;
	border: 1px solid #d88;
	background: #fff5f5;
	color: #a33;
	border-radius: 4px;
}
</style>

<script>
(() => {
	const selector = '[data-base3-display="iliasobjectdebugdisplay"]';
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
