<?php
$translations = is_array($this->_['translations'] ?? null) ? $this->_['translations'] : [];
$t = static function(string $key, string $fallback) use ($translations): string {
	$value = trim((string)($translations[$key] ?? ''));
	return $value !== '' ? $value : $fallback;
};
?>
<div class="base3ilias-user">
	<h3><?php echo htmlspecialchars($t('page_title', 'ILIAS user debug')); ?></h3>

	<div class="user-meta">
		<div><strong><?php echo htmlspecialchars($t('source', 'Source:')); ?></strong> <span class="mono">ilObjUser + ilRbacReview</span></div>
		<div><strong><?php echo htmlspecialchars($t('generated', 'Generated:')); ?></strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['generatedAt']); ?></span></div>
	</div>

	<div class="user-actions">
		<label class="user-ref">
			<?php echo htmlspecialchars($t('user_id_input', 'User ID:')); ?>
			<input type="number" id="user-id" value="<?php echo (int)$this->_['selectedUserId']; ?>" min="1">
		</label>

		<label class="user-ref">
			<?php echo htmlspecialchars($t('login_input', 'Login:')); ?>
			<input type="text" id="user-login" value="<?php echo htmlspecialchars((string)$this->_['selectedLogin']); ?>">
		</label>

		<button type="button" onclick="userApplyParams()"><?php echo htmlspecialchars($t('check_now', 'Check')); ?></button>
		<button type="button" onclick="userUseCurrentUser()"><?php echo htmlspecialchars($t('current_user', 'Current user')); ?></button>

		<div class="user-note">
			<?php echo htmlspecialchars($t('uses_own_url_parameters', 'Uses its own URL parameters:')); ?>
			<span class="mono"><?php echo htmlspecialchars((string)$this->_['userIdParamName']); ?></span>
			<?php echo htmlspecialchars($t('and', 'and')); ?>
			<span class="mono"><?php echo htmlspecialchars((string)$this->_['userLoginParamName']); ?></span>.
		</div>
	</div>

	<?php if ((string)$this->_['selectionMessage'] !== ''): ?>
		<div class="user-message"><?php echo htmlspecialchars((string)$this->_['selectionMessage']); ?></div>
	<?php endif; ?>

	<div class="user-section">
		<div class="user-section-head">
			<h4><?php echo htmlspecialchars($t('user_title', 'User')); ?></h4>
			<div class="user-description"><?php echo htmlspecialchars($t('user_description', 'Basic data for the selected user.')); ?></div>
		</div>

		<div class="user-tablewrap">
			<table class="user-table">
				<thead>
					<tr>
						<th><?php echo htmlspecialchars($t('column_label', 'Label')); ?></th>
						<th><?php echo htmlspecialchars($t('column_key', 'Key')); ?></th>
						<th><?php echo htmlspecialchars($t('column_value', 'Value')); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ((array)$this->_['userRows'] as $row): ?>
						<tr>
							<td class="user-cell-label"><?php echo htmlspecialchars((string)$row['label']); ?></td>
							<td class="user-cell-mono"><?php echo htmlspecialchars((string)$row['key']); ?></td>
							<td class="user-cell-value">
								<?php if ((string)$row['value'] === ''): ?>
									<span class="user-muted">–</span>
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

	<div class="user-section">
		<div class="user-section-head">
			<h4><?php echo htmlspecialchars($t('status_login_title', 'Status / login')); ?></h4>
			<div class="user-description"><?php echo htmlspecialchars($t('status_login_description', 'Account status, login times and time limits.')); ?></div>
		</div>

		<?php if (empty($this->_['statusRows'])): ?>
			<div class="user-empty"><?php echo htmlspecialchars($t('no_status_data', 'No status data is available.')); ?></div>
		<?php else: ?>
			<div class="user-tablewrap">
				<table class="user-table">
					<thead>
						<tr>
							<th><?php echo htmlspecialchars($t('column_label', 'Label')); ?></th>
							<th><?php echo htmlspecialchars($t('column_key', 'Key')); ?></th>
							<th><?php echo htmlspecialchars($t('column_value', 'Value')); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$this->_['statusRows'] as $row): ?>
							<tr>
								<td class="user-cell-label"><?php echo htmlspecialchars((string)$row['label']); ?></td>
								<td class="user-cell-mono"><?php echo htmlspecialchars((string)$row['key']); ?></td>
								<td class="user-cell-value">
									<?php if ((string)$row['value'] === ''): ?>
										<span class="user-muted">–</span>
									<?php else: ?>
										<?php echo htmlspecialchars((string)$row['value']); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="user-section">
		<div class="user-section-head">
			<h4><?php echo htmlspecialchars($t('global_roles_title', 'Global roles')); ?></h4>
			<div class="user-description"><?php echo htmlspecialchars($t('global_roles_description', 'Directly assigned global roles.')); ?></div>
		</div>

		<?php if (empty($this->_['globalRoleRows'])): ?>
			<div class="user-empty"><?php echo htmlspecialchars($t('no_global_roles', 'No global roles found.')); ?></div>
		<?php else: ?>
			<div class="user-tablewrap">
				<table class="user-table">
					<thead>
						<tr>
							<th><?php echo htmlspecialchars($t('column_role_id', 'Role ID')); ?></th>
							<th><?php echo htmlspecialchars($t('column_title', 'Title')); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$this->_['globalRoleRows'] as $row): ?>
							<tr>
								<td class="user-cell-mono"><?php echo htmlspecialchars((string)$row['role_id']); ?></td>
								<td><?php echo htmlspecialchars((string)$row['title']); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="user-section">
		<div class="user-section-head">
			<h4><?php echo htmlspecialchars($t('assigned_roles_title', 'Assigned roles')); ?></h4>
			<div class="user-description"><?php echo htmlspecialchars($t('assigned_roles_description', 'All directly assigned roles.')); ?></div>
		</div>

		<?php if (empty($this->_['roleRows'])): ?>
			<div class="user-empty"><?php echo htmlspecialchars($t('no_roles', 'No roles found.')); ?></div>
		<?php else: ?>
			<div class="user-tablewrap">
				<table class="user-table">
					<thead>
						<tr>
							<th><?php echo htmlspecialchars($t('column_role_id', 'Role ID')); ?></th>
							<th><?php echo htmlspecialchars($t('column_title', 'Title')); ?></th>
							<th><?php echo htmlspecialchars($t('column_type', 'Type')); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$this->_['roleRows'] as $row): ?>
							<tr>
								<td class="user-cell-mono"><?php echo htmlspecialchars((string)$row['role_id']); ?></td>
								<td><?php echo htmlspecialchars((string)$row['title']); ?></td>
								<td class="user-cell-mono"><?php echo htmlspecialchars((string)$row['type']); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="user-section">
		<div class="user-section-head">
			<h4><?php echo htmlspecialchars($t('preferences_title', 'Preferences')); ?></h4>
			<div class="user-description"><?php echo htmlspecialchars($t('preferences_description', 'Selected user preferences.')); ?></div>
		</div>

		<?php if (empty($this->_['preferenceRows'])): ?>
			<div class="user-empty"><?php echo htmlspecialchars($t('no_preferences', 'No preferences found.')); ?></div>
		<?php else: ?>
			<div class="user-tablewrap">
				<table class="user-table">
					<thead>
						<tr>
							<th><?php echo htmlspecialchars($t('column_key', 'Key')); ?></th>
							<th><?php echo htmlspecialchars($t('column_value', 'Value')); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$this->_['preferenceRows'] as $row): ?>
							<tr>
								<td class="user-cell-mono"><?php echo htmlspecialchars((string)$row['key']); ?></td>
								<td class="user-cell-value">
									<?php if ((string)$row['value'] === ''): ?>
										<span class="user-muted">–</span>
									<?php else: ?>
										<?php echo htmlspecialchars((string)$row['value']); ?>
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
.base3ilias-user {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.base3ilias-user h3 {
	margin-top: 0;
	margin-bottom: 12px;
	font-size: 1.1em;
}

.user-meta {
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

.user-actions {
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

.user-ref {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	color: #555;
}

.user-ref input {
	width: 160px;
	padding: 6px 10px;
	border: 1px solid #ccc;
	border-radius: 4px;
	background: #fff;
	color: #333;
}

.user-actions button {
	padding: 8px 16px;
	border: 1px solid #ccc;
	background: #f0f0f0;
	color: #333;
	border-radius: 4px;
	cursor: pointer;
	font-size: 14px;
	transition: background 0.2s, border-color 0.2s;
}

.user-actions button:hover {
	background: #e6e6e6;
	border-color: #bbb;
}

.user-note {
	font-size: 13px;
	color: #666;
}

.user-message {
	margin-bottom: 12px;
	padding: 8px 10px;
	border: 1px solid #e3c07a;
	background: #fffaf0;
	color: #8a5a00;
	border-radius: 4px;
	font-size: 13px;
}

.user-section {
	border-top: 1px solid #eee;
	padding-top: 14px;
	margin-top: 14px;
}

.user-section:first-of-type {
	border-top: 0;
	padding-top: 0;
	margin-top: 0;
}

.user-section-head {
	margin-bottom: 10px;
}

.user-section h4 {
	margin: 0 0 4px 0;
	font-size: 1em;
	color: #333;
}

.user-description {
	font-size: 13px;
	color: #666;
}

.user-tablewrap {
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
}

.user-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.user-table th,
.user-table td {
	border-top: 1px solid #eee;
	padding: 8px 10px;
	vertical-align: top;
	text-align: left;
}

.user-table thead th {
	border-top: 0;
	border-bottom: 1px solid #ddd;
	font-weight: bold;
	white-space: nowrap;
}

.user-cell-label {
	white-space: nowrap;
}

.user-cell-mono {
	font-family: Consolas, monospace;
	white-space: nowrap;
	color: #444;
}

.user-cell-value {
	font-family: Consolas, monospace;
	word-break: break-word;
}

.user-muted,
.user-empty {
	color: #777;
	font-style: italic;
}

.user-empty {
	border-top: 1px solid #eee;
	padding: 8px 10px;
	font-size: 13px;
}
</style>

<script>
	const USER_ID_PARAM = <?php echo json_encode((string)$this->_['userIdParamName']); ?>;
	const USER_LOGIN_PARAM = <?php echo json_encode((string)$this->_['userLoginParamName']); ?>;
	const USER_CURRENT_ID = <?php echo (int)$this->_['currentUserId']; ?>;

	function userApplyParams() {
		const userIdInput = document.getElementById("user-id");
		const loginInput = document.getElementById("user-login");

		const userId = String(userIdInput.value || "").trim();
		const login = String(loginInput.value || "").trim();

		const url = new URL(window.location.href);

		if (login !== "") {
			url.searchParams.set(USER_LOGIN_PARAM, login);
			url.searchParams.delete(USER_ID_PARAM);
		} else if (userId !== "" && userId !== "0") {
			url.searchParams.set(USER_ID_PARAM, userId);
			url.searchParams.delete(USER_LOGIN_PARAM);
		} else {
			url.searchParams.delete(USER_ID_PARAM);
			url.searchParams.delete(USER_LOGIN_PARAM);
		}

		window.location.href = url.toString();
	}

	function userUseCurrentUser() {
		const url = new URL(window.location.href);

		url.searchParams.set(USER_ID_PARAM, String(USER_CURRENT_ID));
		url.searchParams.delete(USER_LOGIN_PARAM);

		window.location.href = url.toString();
	}
</script>
