<div class="base3ilias-request">
	<h3>ILIAS Request Debug</h3>

	<div class="request-meta">
		<div><strong>Quelle:</strong> <span class="mono">ilCtrl + HTTP request data</span></div>
		<div><strong>Generiert:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['generatedAt']); ?></span></div>
	</div>

	<div class="request-section">
		<div class="request-section-head">
			<h4>Controller</h4>
			<div class="request-description">Aktueller ILIAS Controller-Kontext.</div>
		</div>

		<div class="request-tablewrap">
			<table class="request-table">
				<thead>
					<tr>
						<th>Label</th>
						<th>Key</th>
						<th>Value</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ((array)$this->_['controllerRows'] as $row): ?>
						<tr>
							<td class="request-cell-label"><?php echo htmlspecialchars((string)$row['label']); ?></td>
							<td class="request-cell-mono"><?php echo htmlspecialchars((string)$row['key']); ?></td>
							<td class="request-cell-value<?php if ($row['key'] == 'ilCtrl::getCallHistory()') echo ' request-cell-pre-wrap'; ?>">
								<?php if ((string)$row['value'] === ''): ?>
									<span class="request-muted">–</span>
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

	<div class="request-section">
		<div class="request-section-head">
			<h4>Request</h4>
			<div class="request-description">Zentrale HTTP-Request-Werte.</div>
		</div>

		<div class="request-tablewrap">
			<table class="request-table">
				<thead>
					<tr>
						<th>Label</th>
						<th>Key</th>
						<th>Value</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ((array)$this->_['requestRows'] as $row): ?>
						<tr>
							<td class="request-cell-label"><?php echo htmlspecialchars((string)$row['label']); ?></td>
							<td class="request-cell-mono"><?php echo htmlspecialchars((string)$row['key']); ?></td>
							<td class="request-cell-value">
								<?php if ((string)$row['value'] === ''): ?>
									<span class="request-muted">–</span>
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

	<div class="request-section">
		<div class="request-section-head">
			<h4>GET Parameters</h4>
			<div class="request-description">Aus <span class="mono">QUERY_STRING</span> ermittelte Parameter. Sensible Schlüssel werden maskiert.</div>
		</div>

		<?php if (empty($this->_['getRows'])): ?>
			<div class="request-empty">Keine GET-Parameter.</div>
		<?php else: ?>
			<div class="request-tablewrap">
				<table class="request-table">
					<thead>
						<tr>
							<th>Key</th>
							<th>Value</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$this->_['getRows'] as $row): ?>
							<tr>
								<td class="request-cell-mono"><?php echo htmlspecialchars((string)$row['key']); ?></td>
								<td class="request-cell-value">
									<?php if ((string)$row['value'] === ''): ?>
										<span class="request-muted">–</span>
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

	<div class="request-section">
		<div class="request-section-head">
			<h4>POST Parameters</h4>
			<div class="request-description">Über <span class="mono">filter_input_array(INPUT_POST)</span> ermittelte Parameter. Sensible Schlüssel werden maskiert.</div>
		</div>

		<?php if (empty($this->_['postRows'])): ?>
			<div class="request-empty">Keine POST-Parameter.</div>
		<?php else: ?>
			<div class="request-tablewrap">
				<table class="request-table">
					<thead>
						<tr>
							<th>Key</th>
							<th>Value</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$this->_['postRows'] as $row): ?>
							<tr>
								<td class="request-cell-mono"><?php echo htmlspecialchars((string)$row['key']); ?></td>
								<td class="request-cell-value">
									<?php if ((string)$row['value'] === ''): ?>
										<span class="request-muted">–</span>
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

	<div class="request-section">
		<div class="request-section-head">
			<h4>Server Parameters</h4>
			<div class="request-description">Ausgewählte Server-/Header-Werte.</div>
		</div>

		<?php if (empty($this->_['serverRows'])): ?>
			<div class="request-empty">Keine Server-Parameter.</div>
		<?php else: ?>
			<div class="request-tablewrap">
				<table class="request-table">
					<thead>
						<tr>
							<th>Key</th>
							<th>Value</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$this->_['serverRows'] as $row): ?>
							<tr>
								<td class="request-cell-mono"><?php echo htmlspecialchars((string)$row['key']); ?></td>
								<td class="request-cell-value">
									<?php if ((string)$row['value'] === ''): ?>
										<span class="request-muted">–</span>
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
.base3ilias-request {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.base3ilias-request h3 {
	margin-top: 0;
	margin-bottom: 12px;
	font-size: 1.1em;
}

.request-meta {
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

.request-section {
	border-top: 1px solid #eee;
	padding-top: 14px;
	margin-top: 14px;
}

.request-section:first-of-type {
	border-top: 0;
	padding-top: 0;
	margin-top: 0;
}

.request-section-head {
	margin-bottom: 10px;
}

.request-section h4 {
	margin: 0 0 4px 0;
	font-size: 1em;
	color: #333;
}

.request-description {
	font-size: 13px;
	color: #666;
}

.request-tablewrap {
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
}

.request-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.request-table th,
.request-table td {
	border-top: 1px solid #eee;
	padding: 8px 10px;
	vertical-align: top;
	text-align: left;
}

.request-table thead th {
	border-top: 0;
	border-bottom: 1px solid #ddd;
	font-weight: bold;
	white-space: nowrap;
}

.request-cell-label {
	white-space: nowrap;
}

.request-cell-mono {
	font-family: Consolas, monospace;
	white-space: nowrap;
	color: #444;
}

.request-cell-value {
	font-family: Consolas, monospace;
	word-break: break-word;
}

.request-cell-pre-wrap {
	white-space: pre-wrap;
}

.request-muted,
.request-empty {
	color: #777;
	font-style: italic;
}

.request-empty {
	border-top: 1px solid #eee;
	padding: 8px 10px;
	font-size: 13px;
}
</style>
