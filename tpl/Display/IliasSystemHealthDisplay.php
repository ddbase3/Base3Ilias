<div class="base3ilias-health">
	<h3>ILIAS System Health</h3>

	<div class="health-meta">
		<div><strong>Quelle:</strong> <span class="mono">ilIniFile + filesystem checks</span></div>
		<div><strong>Generiert:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['generatedAt']); ?></span></div>
	</div>

	<div class="health-summary health-summary-<?php echo htmlspecialchars((string)$this->_['summary']['status']); ?>">
		<div class="health-summary-main">
			<span class="health-pill <?php echo htmlspecialchars((string)$this->_['summary']['status']); ?>">
				<?php echo htmlspecialchars(strtoupper((string)$this->_['summary']['status'])); ?>
			</span>
			<span><?php echo (int)$this->_['summary']['total']; ?> Checks</span>
		</div>

		<div class="health-summary-counts">
			<span><strong><?php echo (int)$this->_['summary']['ok']; ?></strong> OK</span>
			<span><strong><?php echo (int)$this->_['summary']['warning']; ?></strong> Warning</span>
			<span><strong><?php echo (int)$this->_['summary']['error']; ?></strong> Error</span>
			<span><strong><?php echo (int)$this->_['summary']['info']; ?></strong> Info</span>
		</div>

		<button type="button" onclick="window.location.reload()">Neu prüfen</button>
	</div>

	<?php foreach ((array)$this->_['sections'] as $section): ?>
		<div class="health-section">
			<div class="health-section-head">
				<h4><?php echo htmlspecialchars((string)$section['title']); ?></h4>
				<?php if ((string)$section['description'] !== ''): ?>
					<div class="health-description"><?php echo htmlspecialchars((string)$section['description']); ?></div>
				<?php endif; ?>
			</div>

			<div class="health-tablewrap">
				<table class="health-table">
					<thead>
						<tr>
							<th>Status</th>
							<th>Check</th>
							<th>Source</th>
							<th>Path</th>
							<th>Details</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$section['rows'] as $row): ?>
							<tr>
								<td>
									<span class="health-pill <?php echo htmlspecialchars((string)$row['status']); ?>">
										<?php echo htmlspecialchars(strtoupper((string)$row['status'])); ?>
									</span>
								</td>
								<td>
									<strong><?php echo htmlspecialchars((string)$row['label']); ?></strong>
									<div class="health-type"><?php echo htmlspecialchars((string)$row['type']); ?></div>
								</td>
								<td class="health-cell-mono"><?php echo htmlspecialchars((string)$row['source']); ?></td>
								<td class="health-cell-path">
									<?php if ((string)$row['path'] === ''): ?>
										<span class="health-muted">–</span>
									<?php else: ?>
										<?php echo htmlspecialchars((string)$row['path']); ?>
									<?php endif; ?>
								</td>
								<td>
									<div><?php echo htmlspecialchars((string)$row['message']); ?></div>

									<?php if (!empty($row['meta'])): ?>
										<div class="health-meta-list">
											<?php foreach ((array)$row['meta'] as $meta): ?>
												<span>
													<strong><?php echo htmlspecialchars((string)$meta['label']); ?>:</strong>
													<?php echo htmlspecialchars((string)$meta['value']); ?>
												</span>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<style>
.base3ilias-health {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.base3ilias-health h3 {
	margin-top: 0;
	margin-bottom: 12px;
	font-size: 1.1em;
}

.health-meta {
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

.health-summary {
	border: 1px solid #ddd;
	background: #f8f8f8;
	border-radius: 4px;
	padding: 12px;
	margin-bottom: 16px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: wrap;
}

.health-summary-main {
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 14px;
}

.health-summary-counts {
	display: flex;
	align-items: center;
	gap: 14px;
	font-size: 13px;
	color: #555;
	flex-wrap: wrap;
}

.health-summary button {
	padding: 8px 16px;
	border: 1px solid #ccc;
	background: #f0f0f0;
	color: #333;
	border-radius: 4px;
	cursor: pointer;
	font-size: 14px;
	transition: background 0.2s, border-color 0.2s;
}

.health-summary button:hover {
	background: #e6e6e6;
	border-color: #bbb;
}

.health-section {
	border-top: 1px solid #eee;
	padding-top: 14px;
	margin-top: 14px;
}

.health-section:first-of-type {
	border-top: 0;
	padding-top: 0;
	margin-top: 0;
}

.health-section-head {
	margin-bottom: 10px;
}

.health-section h4 {
	margin: 0 0 4px 0;
	font-size: 1em;
	color: #333;
}

.health-description {
	font-size: 13px;
	color: #666;
}

.health-tablewrap {
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
}

.health-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.health-table th,
.health-table td {
	border-top: 1px solid #eee;
	padding: 8px 10px;
	vertical-align: top;
	text-align: left;
}

.health-table thead th {
	border-top: 0;
	border-bottom: 1px solid #ddd;
	font-weight: bold;
	white-space: nowrap;
}

.health-pill {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid #ccc;
	background: #f6f6f6;
	font-size: 12px;
	white-space: nowrap;
	font-weight: bold;
}

.health-pill.ok {
	border-color: #8d8;
	background: #f6fff6;
	color: #2d6a2d;
}

.health-pill.warning {
	border-color: #e3c07a;
	background: #fffaf0;
	color: #8a5a00;
}

.health-pill.error {
	border-color: #d88;
	background: #fff5f5;
	color: #a33;
}

.health-pill.info {
	border-color: #9cd;
	background: #f3fbff;
	color: #135a7a;
}

.health-type {
	margin-top: 2px;
	font-size: 12px;
	color: #777;
}

.health-cell-mono {
	font-family: Consolas, monospace;
	white-space: nowrap;
	color: #444;
}

.health-cell-path {
	font-family: Consolas, monospace;
	white-space: normal;
	word-break: break-word;
}

.health-muted {
	color: #777;
	font-style: italic;
}

.health-meta-list {
	margin-top: 6px;
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	color: #666;
	font-size: 12px;
}

.health-meta-list span {
	display: inline-block;
	padding: 2px 6px;
	border: 1px solid #ddd;
	border-radius: 4px;
	background: #f8f8f8;
}
</style>
