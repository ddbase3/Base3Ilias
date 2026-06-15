<div class="base3ilias-object">
	<h3>ILIAS Object Debug</h3>

	<div class="object-meta">
		<div><strong>Quelle:</strong> <span class="mono">ilObject + ilTree</span></div>
		<div><strong>Generiert:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['generatedAt']); ?></span></div>
	</div>

	<div class="object-actions">
		<label class="object-ref">
			Target ref_id:
			<input type="number" id="object-target-ref-id" value="<?php echo (int)$this->_['targetRefId']; ?>" min="1">
		</label>

		<button type="button" onclick="objectApplyParams()">Prüfen</button>

		<div class="object-note">
			Verwendet eigenen URL-Parameter:
			<span class="mono"><?php echo htmlspecialchars((string)$this->_['targetParamName']); ?></span>.
			<span class="mono">ref_id</span> wird nicht verändert.
		</div>
	</div>

	<div class="object-section">
		<div class="object-section-head">
			<h4>Object</h4>
			<div class="object-description">Basisdaten zum Zielobjekt.</div>
		</div>

		<div class="object-tablewrap">
			<table class="object-table">
				<thead>
					<tr>
						<th>Label</th>
						<th>Key</th>
						<th>Value</th>
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
			<h4>Repository Path</h4>
			<div class="object-description">Pfad vom Repository-Root bis zum Zielobjekt.</div>
		</div>

		<?php if (empty($this->_['pathRows'])): ?>
			<div class="object-empty">Kein Pfad vorhanden oder kein Target ref_id angegeben.</div>
		<?php else: ?>
			<div class="object-tablewrap">
				<table class="object-table">
					<thead>
						<tr>
							<th>Depth</th>
							<th>Ref ID</th>
							<th>Obj ID</th>
							<th>Type</th>
							<th>Title</th>
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
			<h4>Direct Children</h4>
			<div class="object-description">Direkte Kinder des Zielobjekts, begrenzt auf <?php echo (int)$this->_['maxChildren']; ?> Einträge.</div>
		</div>

		<?php if (empty($this->_['childRows'])): ?>
			<div class="object-empty">Keine direkten Kinder gefunden oder kein Target ref_id angegeben.</div>
		<?php else: ?>
			<div class="object-tablewrap">
				<table class="object-table">
					<thead>
						<tr>
							<th>Ref ID</th>
							<th>Obj ID</th>
							<th>Type</th>
							<th>Title</th>
							<th>Description</th>
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
</style>

<script>
	const OBJECT_TARGET_PARAM = <?php echo json_encode((string)$this->_['targetParamName']); ?>;

	function objectApplyParams() {
		const targetInput = document.getElementById("object-target-ref-id");
		const targetRefId = String(targetInput.value || "").trim();
		const url = new URL(window.location.href);

		if (targetRefId === "" || targetRefId === "0") {
			url.searchParams.delete(OBJECT_TARGET_PARAM);
		} else {
			url.searchParams.set(OBJECT_TARGET_PARAM, targetRefId);
		}

		window.location.href = url.toString();
	}
</script>
