<div class="base3ilias-config">
	<h3>ILIAS Configuration</h3>

	<div class="config-meta">
		<div><strong>Quelle:</strong> <span class="mono">ilIniFile</span></div>
		<div><strong>Generiert:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['generatedAt']); ?></span></div>
	</div>

	<?php foreach ((array)$this->_['sections'] as $section): ?>
		<div class="config-section">
			<div class="config-section-head">
				<h4><?php echo htmlspecialchars((string)$section['title']); ?></h4>
				<?php if ((string)$section['description'] !== ''): ?>
					<div class="config-description"><?php echo htmlspecialchars((string)$section['description']); ?></div>
				<?php endif; ?>
			</div>

			<div class="config-tablewrap">
				<table class="config-table">
					<thead>
						<tr>
							<th>Label</th>
							<th>Section</th>
							<th>Key</th>
							<th>Value</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$section['rows'] as $row): ?>
							<tr>
								<td><?php echo htmlspecialchars((string)$row['label']); ?></td>
								<td class="config-cell-mono"><?php echo htmlspecialchars((string)$row['section']); ?></td>
								<td class="config-cell-mono"><?php echo htmlspecialchars((string)$row['key']); ?></td>
								<td class="config-cell-value<?php echo $row['empty'] ? ' config-muted' : ''; ?>">
									<?php if ($row['empty']): ?>
										–
									<?php elseif ($row['sensitive']): ?>
										<span class="config-sensitive"><?php echo htmlspecialchars((string)$row['value']); ?></span>
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
	<?php endforeach; ?>
</div>

<style>
.base3ilias-config {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.base3ilias-config h3 {
	margin-top: 0;
	margin-bottom: 12px;
	font-size: 1.1em;
}

.config-meta {
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

.config-section {
	border-top: 1px solid #eee;
	padding-top: 14px;
	margin-top: 14px;
}

.config-section:first-of-type {
	border-top: 0;
	padding-top: 0;
	margin-top: 0;
}

.config-section-head {
	margin-bottom: 10px;
}

.config-section h4 {
	margin: 0 0 4px 0;
	font-size: 1em;
	color: #333;
}

.config-description {
	font-size: 13px;
	color: #666;
}

.config-tablewrap {
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
}

.config-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.config-table th,
.config-table td {
	border-top: 1px solid #eee;
	padding: 8px 10px;
	vertical-align: top;
	text-align: left;
}

.config-table thead th {
	border-top: 0;
	border-bottom: 1px solid #ddd;
	font-weight: bold;
	white-space: nowrap;
}

.config-cell-mono {
	font-family: Consolas, monospace;
	white-space: nowrap;
	color: #444;
}

.config-cell-value {
	font-family: Consolas, monospace;
	white-space: normal;
	word-break: break-word;
}

.config-muted {
	color: #777;
	font-style: italic;
}

.config-sensitive {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid #ddd;
	background: #f6f6f6;
	color: #555;
	letter-spacing: 1px;
}
</style>
