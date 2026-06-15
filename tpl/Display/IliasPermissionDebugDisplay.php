<div class="base3ilias-permission">
	<h3>ILIAS Permission Debug</h3>

	<div class="permission-meta">
		<div><strong>Quelle:</strong> <span class="mono">ilObjUser + ilRbacReview</span></div>
		<div><strong>Generiert:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['generatedAt']); ?></span></div>
	</div>

	<div class="permission-actions">
		<label class="permission-ref">
			Target ref_id:
			<input type="number" id="permission-target-ref-id" value="<?php echo (int)$this->_['targetRefId']; ?>" min="1">
		</label>

		<label class="permission-ref">
			User ID:
			<input type="number" id="permission-user-id" value="<?php echo (int)$this->_['userId']; ?>" min="1">
		</label>

		<button type="button" onclick="permissionApplyParams()">Prüfen</button>
		<button type="button" onclick="permissionUseCurrentUser()">Aktueller User</button>

		<div class="permission-note">
			Verwendet eigene URL-Parameter:
			<span class="mono"><?php echo htmlspecialchars((string)$this->_['targetParamName']); ?></span>
			und
			<span class="mono"><?php echo htmlspecialchars((string)$this->_['userParamName']); ?></span>.
			<span class="mono">ref_id</span> wird nicht verändert.
		</div>
	</div>

	<div class="permission-section">
		<div class="permission-section-head">
			<h4>Target Object</h4>
			<div class="permission-description">Zielobjekt für rollenbasierte Rechteprüfung.</div>
		</div>

		<div class="permission-tablewrap">
			<table class="permission-table">
				<thead>
					<tr>
						<th>Label</th>
						<th>Key</th>
						<th>Value</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ((array)$this->_['targetRows'] as $row): ?>
						<tr>
							<td class="permission-cell-label"><?php echo htmlspecialchars((string)$row['label']); ?></td>
							<td class="permission-cell-mono"><?php echo htmlspecialchars((string)$row['key']); ?></td>
							<td class="permission-cell-value">
								<?php if ((string)$row['value'] === ''): ?>
									<span class="permission-muted">–</span>
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

	<div class="permission-section">
		<div class="permission-section-head">
			<h4>Selected User</h4>
			<div class="permission-description">Benutzer, dessen Rollen geprüft werden.</div>
		</div>

		<div class="permission-tablewrap">
			<table class="permission-table">
				<thead>
					<tr>
						<th>Label</th>
						<th>Key</th>
						<th>Value</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ((array)$this->_['userRows'] as $row): ?>
						<tr>
							<td class="permission-cell-label"><?php echo htmlspecialchars((string)$row['label']); ?></td>
							<td class="permission-cell-mono"><?php echo htmlspecialchars((string)$row['key']); ?></td>
							<td class="permission-cell-value">
								<?php if ((string)$row['value'] === ''): ?>
									<span class="permission-muted">–</span>
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

	<div class="permission-section">
		<div class="permission-section-head">
			<h4>Effective RBAC Operations</h4>
			<div class="permission-description">Aus den dem ausgewählten User zugewiesenen Rollen im Zielpfad abgeleitete Operationen.</div>
		</div>

		<?php if (empty($this->_['effectiveRows'])): ?>
			<div class="permission-empty">Keine Operationen ermittelbar.</div>
		<?php else: ?>
			<div class="permission-tablewrap">
				<table class="permission-table">
					<thead>
						<tr>
							<th>Status</th>
							<th>Operation</th>
							<th>Operation ID</th>
							<th>Granted By</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$this->_['effectiveRows'] as $row): ?>
							<tr>
								<td>
									<?php if (!empty($row['granted'])): ?>
										<span class="permission-pill ok">YES</span>
									<?php else: ?>
										<span class="permission-pill denied">NO</span>
									<?php endif; ?>
								</td>
								<td class="permission-cell-mono"><?php echo htmlspecialchars((string)$row['operation']); ?></td>
								<td class="permission-cell-mono">
									<?php if ((string)$row['operation_id'] === ''): ?>
										<span class="permission-muted">–</span>
									<?php else: ?>
										<?php echo htmlspecialchars((string)$row['operation_id']); ?>
									<?php endif; ?>
								</td>
								<td class="permission-cell-value">
									<?php if ((string)$row['granted_by'] === ''): ?>
										<span class="permission-muted">–</span>
									<?php else: ?>
										<?php echo htmlspecialchars((string)$row['granted_by']); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="permission-section">
		<div class="permission-section-head">
			<h4>Role Permissions On Target</h4>
			<div class="permission-description">Relevante Rollen: User-Rollen, die auch im Rollenpfad des Zielobjekts vorkommen.</div>
		</div>

		<?php if (empty($this->_['rolePermissionRows'])): ?>
			<div class="permission-empty">Keine relevanten Rollen für dieses Zielobjekt gefunden.</div>
		<?php else: ?>
			<div class="permission-tablewrap">
				<table class="permission-table">
					<thead>
						<tr>
							<th>Role ID</th>
							<th>Title</th>
							<th>Type</th>
							<th>Parent</th>
							<th>Operations</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$this->_['rolePermissionRows'] as $row): ?>
							<tr>
								<td class="permission-cell-mono"><?php echo htmlspecialchars((string)$row['role_id']); ?></td>
								<td><?php echo htmlspecialchars((string)$row['title']); ?></td>
								<td class="permission-cell-mono">
									<?php if ((string)$row['type'] === ''): ?>
										<span class="permission-muted">–</span>
									<?php else: ?>
										<?php echo htmlspecialchars((string)$row['type']); ?>
									<?php endif; ?>
								</td>
								<td class="permission-cell-mono">
									<?php if ((string)$row['parent'] === ''): ?>
										<span class="permission-muted">–</span>
									<?php else: ?>
										<?php echo htmlspecialchars((string)$row['parent']); ?>
									<?php endif; ?>
								</td>
								<td class="permission-cell-value">
									<?php if ((string)$row['operations'] === ''): ?>
										<span class="permission-muted">–</span>
									<?php else: ?>
										<?php echo nl2br(htmlspecialchars((string)$row['operations'])); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="permission-section">
		<div class="permission-section-head">
			<h4>Assigned User Roles</h4>
			<div class="permission-description">Alle dem ausgewählten User direkt zugewiesenen Rollen.</div>
		</div>

		<?php if (empty($this->_['assignedRoleRows'])): ?>
			<div class="permission-empty">Keine Rollen gefunden.</div>
		<?php else: ?>
			<div class="permission-tablewrap">
				<table class="permission-table">
					<thead>
						<tr>
							<th>Role ID</th>
							<th>Title</th>
							<th>Type</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$this->_['assignedRoleRows'] as $row): ?>
							<tr>
								<td class="permission-cell-mono"><?php echo htmlspecialchars((string)$row['role_id']); ?></td>
								<td><?php echo htmlspecialchars((string)$row['title']); ?></td>
								<td class="permission-cell-mono"><?php echo htmlspecialchars((string)$row['type']); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="permission-section">
		<div class="permission-section-head">
			<h4>Roles In Target Path</h4>
			<div class="permission-description">Rollen, die für das Zielobjekt über den Repository-Pfad relevant sind.</div>
		</div>

		<?php if (empty($this->_['parentRoleRows'])): ?>
			<div class="permission-empty">Keine Rollen im Zielpfad gefunden oder kein Target ref_id angegeben.</div>
		<?php else: ?>
			<div class="permission-tablewrap">
				<table class="permission-table">
					<thead>
						<tr>
							<th>Assigned</th>
							<th>Role ID</th>
							<th>Title</th>
							<th>Type</th>
							<th>Parent</th>
							<th>Protected</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$this->_['parentRoleRows'] as $row): ?>
							<tr>
								<td>
									<?php if (!empty($row['assigned'])): ?>
										<span class="permission-pill ok">YES</span>
									<?php else: ?>
										<span class="permission-pill info">NO</span>
									<?php endif; ?>
								</td>
								<td class="permission-cell-mono"><?php echo htmlspecialchars((string)$row['role_id']); ?></td>
								<td><?php echo htmlspecialchars((string)$row['title']); ?></td>
								<td class="permission-cell-mono">
									<?php if ((string)$row['type'] === ''): ?>
										<span class="permission-muted">–</span>
									<?php else: ?>
										<?php echo htmlspecialchars((string)$row['type']); ?>
									<?php endif; ?>
								</td>
								<td class="permission-cell-mono">
									<?php if ((string)$row['parent'] === ''): ?>
										<span class="permission-muted">–</span>
									<?php else: ?>
										<?php echo htmlspecialchars((string)$row['parent']); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php if (!empty($row['protected'])): ?>
										<span class="permission-pill warning">YES</span>
									<?php else: ?>
										<span class="permission-pill info">NO</span>
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
.base3ilias-permission {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.base3ilias-permission h3 {
	margin-top: 0;
	margin-bottom: 12px;
	font-size: 1.1em;
}

.permission-meta {
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

.permission-actions {
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

.permission-ref {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	color: #555;
}

.permission-ref input {
	width: 120px;
	padding: 6px 10px;
	border: 1px solid #ccc;
	border-radius: 4px;
	background: #fff;
	color: #333;
}

.permission-actions button {
	padding: 8px 16px;
	border: 1px solid #ccc;
	background: #f0f0f0;
	color: #333;
	border-radius: 4px;
	cursor: pointer;
	font-size: 14px;
	transition: background 0.2s, border-color 0.2s;
}

.permission-actions button:hover {
	background: #e6e6e6;
	border-color: #bbb;
}

.permission-note {
	font-size: 13px;
	color: #666;
}

.permission-section {
	border-top: 1px solid #eee;
	padding-top: 14px;
	margin-top: 14px;
}

.permission-section:first-of-type {
	border-top: 0;
	padding-top: 0;
	margin-top: 0;
}

.permission-section-head {
	margin-bottom: 10px;
}

.permission-section h4 {
	margin: 0 0 4px 0;
	font-size: 1em;
	color: #333;
}

.permission-description {
	font-size: 13px;
	color: #666;
}

.permission-tablewrap {
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
}

.permission-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.permission-table th,
.permission-table td {
	border-top: 1px solid #eee;
	padding: 8px 10px;
	vertical-align: top;
	text-align: left;
}

.permission-table thead th {
	border-top: 0;
	border-bottom: 1px solid #ddd;
	font-weight: bold;
	white-space: nowrap;
}

.permission-cell-label {
	white-space: nowrap;
}

.permission-cell-mono {
	font-family: Consolas, monospace;
	white-space: nowrap;
	color: #444;
}

.permission-cell-value {
	font-family: Consolas, monospace;
	word-break: break-word;
}

.permission-cell-pre-wrap {
	white-space: pre-wrap;
}

.permission-muted,
.permission-empty {
	color: #777;
	font-style: italic;
}

.permission-empty {
	border-top: 1px solid #eee;
	padding: 8px 10px;
	font-size: 13px;
}

.permission-pill {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid #ccc;
	background: #f6f6f6;
	font-size: 12px;
	white-space: nowrap;
	font-weight: bold;
}

.permission-pill.ok {
	border-color: #8d8;
	background: #f6fff6;
	color: #2d6a2d;
}

.permission-pill.denied {
	border-color: #d88;
	background: #fff5f5;
	color: #a33;
}

.permission-pill.warning {
	border-color: #e3c07a;
	background: #fffaf0;
	color: #8a5a00;
}

.permission-pill.info {
	border-color: #9cd;
	background: #f3fbff;
	color: #135a7a;
}
</style>

<script>
	const PERMISSION_TARGET_PARAM = <?php echo json_encode((string)$this->_['targetParamName']); ?>;
	const PERMISSION_USER_PARAM = <?php echo json_encode((string)$this->_['userParamName']); ?>;
	const PERMISSION_CURRENT_USER_ID = <?php echo (int)$this->_['currentUserId']; ?>;

	function permissionApplyParams() {
		const targetInput = document.getElementById("permission-target-ref-id");
		const userInput = document.getElementById("permission-user-id");

		const targetRefId = String(targetInput.value || "").trim();
		const userId = String(userInput.value || "").trim();

		const url = new URL(window.location.href);

		if (targetRefId === "" || targetRefId === "0") {
			url.searchParams.delete(PERMISSION_TARGET_PARAM);
		 } else {
			url.searchParams.set(PERMISSION_TARGET_PARAM, targetRefId);
		}

		if (userId === "" || userId === "0") {
			url.searchParams.delete(PERMISSION_USER_PARAM);
		} else {
			url.searchParams.set(PERMISSION_USER_PARAM, userId);
		}

		window.location.href = url.toString();
	}

	function permissionUseCurrentUser() {
		document.getElementById("permission-user-id").value = String(PERMISSION_CURRENT_USER_ID);
		permissionApplyParams();
	}
</script>
