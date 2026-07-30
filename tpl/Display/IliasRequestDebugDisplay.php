<?php
$translations = is_array($this->_['translations'] ?? null) ? $this->_['translations'] : [];
$t = static function(string $key, string $fallback) use ($translations): string {
	$value = trim((string)($translations[$key] ?? ''));
	return $value !== '' ? $value : $fallback;
};
?>
<div class="base3ilias-request">
	<h3><?php echo htmlspecialchars($t('page_title', 'ILIAS request debug')); ?></h3>

	<div class="request-meta">
		<div><strong><?php echo htmlspecialchars($t('source', 'Source:')); ?></strong> <span class="mono">ilCtrl + HTTP request data</span></div>
		<div><strong><?php echo htmlspecialchars($t('generated', 'Generated:')); ?></strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['generatedAt']); ?></span></div>
	</div>

	<div class="request-section">
		<div class="request-section-head">
			<h4><?php echo htmlspecialchars($t('controller_title', 'Controller')); ?></h4>
			<div class="request-description"><?php echo htmlspecialchars($t('controller_description', 'Current ILIAS controller context.')); ?></div>
		</div>

		<div class="request-tablewrap">
			<table class="request-table">
				<thead>
					<tr>
						<th><?php echo htmlspecialchars($t('column_label', 'Label')); ?></th>
						<th><?php echo htmlspecialchars($t('column_key', 'Key')); ?></th>
						<th><?php echo htmlspecialchars($t('column_value', 'Value')); ?></th>
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
			<h4><?php echo htmlspecialchars($t('request_title', 'Request')); ?></h4>
			<div class="request-description"><?php echo htmlspecialchars($t('request_description', 'Core HTTP request values.')); ?></div>
		</div>

		<div class="request-tablewrap">
			<table class="request-table">
				<thead>
					<tr>
						<th><?php echo htmlspecialchars($t('column_label', 'Label')); ?></th>
						<th><?php echo htmlspecialchars($t('column_key', 'Key')); ?></th>
						<th><?php echo htmlspecialchars($t('column_value', 'Value')); ?></th>
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
			<h4><?php echo htmlspecialchars($t('get_parameters_title', 'GET parameters')); ?></h4>
			<div class="request-description"><?php echo htmlspecialchars($t('get_parameters_description_prefix', 'Parameters derived from')); ?> <span class="mono">QUERY_STRING</span>. <?php echo htmlspecialchars($t('sensitive_keys_masked', 'Sensitive keys are masked.')); ?></div>
		</div>

		<?php if (empty($this->_['getRows'])): ?>
			<div class="request-empty"><?php echo htmlspecialchars($t('no_get_parameters', 'No GET parameters.')); ?></div>
		<?php else: ?>
			<div class="request-tablewrap">
				<table class="request-table">
					<thead>
						<tr>
							<th><?php echo htmlspecialchars($t('column_key', 'Key')); ?></th>
							<th><?php echo htmlspecialchars($t('column_value', 'Value')); ?></th>
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
			<h4><?php echo htmlspecialchars($t('post_parameters_title', 'POST parameters')); ?></h4>
			<div class="request-description"><?php echo htmlspecialchars($t('post_parameters_description_prefix', 'Parameters obtained through')); ?> <span class="mono">filter_input_array(INPUT_POST)</span>. <?php echo htmlspecialchars($t('sensitive_keys_masked', 'Sensitive keys are masked.')); ?></div>
		</div>

		<?php if (empty($this->_['postRows'])): ?>
			<div class="request-empty"><?php echo htmlspecialchars($t('no_post_parameters', 'No POST parameters.')); ?></div>
		<?php else: ?>
			<div class="request-tablewrap">
				<table class="request-table">
					<thead>
						<tr>
							<th><?php echo htmlspecialchars($t('column_key', 'Key')); ?></th>
							<th><?php echo htmlspecialchars($t('column_value', 'Value')); ?></th>
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
			<h4><?php echo htmlspecialchars($t('server_parameters_title', 'Server parameters')); ?></h4>
			<div class="request-description"><?php echo htmlspecialchars($t('server_parameters_description', 'Selected server and header values.')); ?></div>
		</div>

		<?php if (empty($this->_['serverRows'])): ?>
			<div class="request-empty"><?php echo htmlspecialchars($t('no_server_parameters', 'No server parameters.')); ?></div>
		<?php else: ?>
			<div class="request-tablewrap">
				<table class="request-table">
					<thead>
						<tr>
							<th><?php echo htmlspecialchars($t('column_key', 'Key')); ?></th>
							<th><?php echo htmlspecialchars($t('column_value', 'Value')); ?></th>
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
