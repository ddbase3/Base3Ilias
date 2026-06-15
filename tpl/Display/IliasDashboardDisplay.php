<div class="base3ilias-dashboard">
	<div class="dashboard-head">
		<div>
			<h3>ILIAS Admin Dashboard</h3>
			<div class="dashboard-subtitle">Kompakte Startseite für Systemstatus, Logs und Debug-Werkzeuge.</div>
		</div>

		<div class="dashboard-generated">
			<strong>Generiert:</strong>
			<span class="mono"><?php echo htmlspecialchars((string)$this->_['generatedAt']); ?></span>
		</div>
	</div>

	<div class="dashboard-hero dashboard-status-<?php echo htmlspecialchars((string)$this->_['summary']['status']); ?>">
		<div class="dashboard-ring" style="--dashboard-score: <?php echo (int)$this->_['summary']['score']; ?>;">
			<div>
				<strong><?php echo (int)$this->_['summary']['score']; ?>%</strong>
				<span>OK</span>
			</div>
		</div>

		<div class="dashboard-hero-text">
			<div class="dashboard-status-pill <?php echo htmlspecialchars((string)$this->_['summary']['status']); ?>">
				<?php echo htmlspecialchars(strtoupper((string)$this->_['summary']['status'])); ?>
			</div>
			<h4><?php echo htmlspecialchars((string)$this->_['summary']['message']); ?></h4>
			<div class="dashboard-hero-meta">
				<span><?php echo (int)$this->_['summary']['ok']; ?> OK</span>
				<span><?php echo (int)$this->_['summary']['warning']; ?> Warnungen</span>
				<span><?php echo (int)$this->_['summary']['error']; ?> Fehler</span>
				<span><?php echo (int)$this->_['summary']['total']; ?> Checks</span>
			</div>
		</div>
	</div>

	<div class="dashboard-card-grid">
		<?php foreach ((array)$this->_['cards'] as $card): ?>
			<div class="dashboard-card dashboard-card-<?php echo htmlspecialchars((string)$card['type']); ?>">
				<div class="dashboard-card-top">
					<div class="dashboard-card-title"><?php echo htmlspecialchars((string)$card['title']); ?></div>
					<div class="dashboard-mini-pill <?php echo htmlspecialchars((string)$card['status']); ?>">
						<?php echo htmlspecialchars(strtoupper((string)$card['status'])); ?>
					</div>
				</div>

				<div class="dashboard-card-value"><?php echo htmlspecialchars((string)$card['value']); ?></div>
				<div class="dashboard-card-meta"><?php echo htmlspecialchars((string)$card['meta']); ?></div>

				<?php if (!empty($card['items'])): ?>
					<div class="dashboard-mini-list">
						<?php foreach ((array)$card['items'] as $label => $value): ?>
							<div>
								<span><?php echo htmlspecialchars((string)$label); ?></span>
								<strong>
									<?php if ((string)$value === ''): ?>
										–
									<?php else: ?>
										<?php echo htmlspecialchars((string)$value); ?>
									<?php endif; ?>
								</strong>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="dashboard-lower-grid">
		<div class="dashboard-panel">
			<div class="dashboard-panel-head">
				<h4>Basisprüfungen</h4>
				<div>Kurzer Status der wichtigsten Pfade und Dateien.</div>
			</div>

			<div class="dashboard-check-grid">
				<?php foreach ((array)$this->_['pathChecks'] as $check): ?>
					<div class="dashboard-check dashboard-check-<?php echo htmlspecialchars((string)$check['status']); ?>" title="<?php echo htmlspecialchars((string)$check['path']); ?>">
						<span class="dashboard-dot <?php echo htmlspecialchars((string)$check['status']); ?>"></span>
						<div>
							<strong><?php echo htmlspecialchars((string)$check['label']); ?></strong>
							<span><?php echo htmlspecialchars((string)$check['message']); ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="dashboard-panel">
			<div class="dashboard-panel-head">
				<h4>Aktivität</h4>
				<div>Letzte bekannte Zeitpunkte.</div>
			</div>

			<div class="dashboard-timeline">
				<?php foreach ((array)$this->_['timelineItems'] as $item): ?>
					<div class="dashboard-timeline-item">
						<span class="dashboard-dot <?php echo htmlspecialchars((string)$item['status']); ?>"></span>
						<div>
							<strong><?php echo htmlspecialchars((string)$item['label']); ?></strong>
							<span><?php echo htmlspecialchars((string)$item['value']); ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<div class="dashboard-panel dashboard-tools">
		<div class="dashboard-panel-head">
			<h4>Werkzeuge</h4>
			<div>Direkte Einstiege in die spezialisierten Admin-Displays im ILIAS-Rahmen.</div>
		</div>

		<div class="dashboard-tool-grid">
			<?php foreach ((array)$this->_['quickLinks'] as $link): ?>
				<a class="dashboard-tool-card dashboard-tool-<?php echo htmlspecialchars((string)$link['type']); ?>" href="<?php echo htmlspecialchars((string)$link['url']); ?>">
					<strong><?php echo htmlspecialchars((string)$link['title']); ?></strong>
					<span><?php echo htmlspecialchars((string)$link['description']); ?></span>
					<em><?php echo htmlspecialchars((string)$link['command']); ?></em>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<style>
.base3ilias-dashboard {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.base3ilias-dashboard h3 {
	margin-top: 0;
	margin-bottom: 4px;
	font-size: 1.2em;
}

.base3ilias-dashboard h4 {
	margin: 0 0 4px 0;
	font-size: 1em;
	color: #333;
}

.mono {
	font-family: Consolas, monospace;
}

.dashboard-head {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 16px;
	flex-wrap: wrap;
	margin-bottom: 16px;
}

.dashboard-subtitle,
.dashboard-generated {
	font-size: 13px;
	color: #666;
}

.dashboard-hero {
	border: 1px solid #ddd;
	background: #f8f8f8;
	border-radius: 6px;
	padding: 16px;
	margin-bottom: 16px;
	display: flex;
	align-items: center;
	gap: 18px;
}

.dashboard-ring {
	width: 112px;
	height: 112px;
	border-radius: 999px;
	background:
		conic-gradient(#8d8 calc(var(--dashboard-score) * 1%), #eee 0);
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
}

.dashboard-ring > div {
	width: 78px;
	height: 78px;
	border-radius: 999px;
	background: #fff;
	border: 1px solid #ddd;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-direction: column;
}

.dashboard-ring strong {
	font-size: 22px;
	line-height: 1;
}

.dashboard-ring span {
	font-size: 11px;
	color: #666;
	margin-top: 4px;
}

.dashboard-hero-text h4 {
	margin-top: 8px;
	margin-bottom: 8px;
	font-size: 1.05em;
}

.dashboard-hero-meta {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	font-size: 13px;
	color: #555;
}

.dashboard-hero-meta span {
	border: 1px solid #ddd;
	background: #fff;
	border-radius: 999px;
	padding: 3px 8px;
}

.dashboard-status-pill,
.dashboard-mini-pill {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid #ccc;
	background: #f6f6f6;
	font-size: 12px;
	white-space: nowrap;
	font-weight: bold;
}

.dashboard-status-pill {
	padding: 5px 12px;
	font-size: 13px;
}

.dashboard-status-pill.ok,
.dashboard-mini-pill.ok,
.dashboard-dot.ok {
	border-color: #8d8;
	background: #f6fff6;
	color: #2d6a2d;
}

.dashboard-status-pill.warning,
.dashboard-mini-pill.warning,
.dashboard-dot.warning {
	border-color: #e3c07a;
	background: #fffaf0;
	color: #8a5a00;
}

.dashboard-status-pill.error,
.dashboard-mini-pill.error,
.dashboard-dot.error {
	border-color: #d88;
	background: #fff5f5;
	color: #a33;
}

.dashboard-status-pill.info,
.dashboard-mini-pill.info,
.dashboard-dot.info {
	border-color: #9cd;
	background: #f3fbff;
	color: #135a7a;
}

.dashboard-card-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 12px;
	margin-bottom: 16px;
}

.dashboard-card,
.dashboard-panel {
	border: 1px solid #ddd;
	background: #fafafa;
	border-radius: 6px;
	padding: 12px;
}

.dashboard-card-top {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 8px;
	margin-bottom: 8px;
}

.dashboard-card-title {
	font-size: 13px;
	color: #555;
	font-weight: bold;
}

.dashboard-card-value {
	font-size: 19px;
	font-weight: bold;
	color: #333;
	margin-bottom: 4px;
	word-break: break-word;
}

.dashboard-card-meta {
	font-size: 12px;
	color: #666;
	word-break: break-word;
	min-height: 16px;
}

.dashboard-mini-list {
	margin-top: 10px;
	border-top: 1px solid #eee;
	padding-top: 8px;
	display: grid;
	gap: 5px;
}

.dashboard-mini-list div {
	display: flex;
	justify-content: space-between;
	gap: 10px;
	font-size: 12px;
}

.dashboard-mini-list span {
	color: #666;
}

.dashboard-mini-list strong {
	font-family: Consolas, monospace;
	font-weight: normal;
	text-align: right;
	word-break: break-word;
}

.dashboard-lower-grid {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(280px, 360px);
	gap: 12px;
	margin-bottom: 16px;
}

.dashboard-panel-head {
	margin-bottom: 10px;
}

.dashboard-panel-head div {
	font-size: 13px;
	color: #666;
}

.dashboard-check-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
	gap: 8px;
}

.dashboard-check,
.dashboard-timeline-item {
	border: 1px solid #eee;
	background: #fff;
	border-radius: 4px;
	padding: 8px;
	display: flex;
	gap: 8px;
	align-items: flex-start;
}

.dashboard-check strong,
.dashboard-timeline-item strong {
	display: block;
	font-size: 13px;
	color: #333;
	margin-bottom: 2px;
}

.dashboard-check span,
.dashboard-timeline-item span {
	font-size: 12px;
	color: #666;
}

.dashboard-dot {
	width: 11px;
	height: 11px;
	border-radius: 999px;
	border: 1px solid #ccc;
	flex: 0 0 auto;
	margin-top: 2px;
}

.dashboard-timeline {
	display: grid;
	gap: 8px;
}

.dashboard-tool-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
	gap: 8px;
}

.dashboard-tool-card {
	display: block;
	border: 1px solid #ddd;
	background: #fff;
	border-radius: 6px;
	padding: 10px;
	text-decoration: none;
	color: #333;
	min-height: 82px;
}

.dashboard-tool-card:hover {
	background: #f3fbff;
	border-color: #9cd;
	text-decoration: none;
}

.dashboard-tool-card strong {
	display: block;
	margin-bottom: 4px;
	font-size: 14px;
	color: #135a7a;
}

.dashboard-tool-card span {
	display: block;
	font-size: 12px;
	color: #666;
	line-height: 1.35;
}

.dashboard-tool-card em {
	display: block;
	margin-top: 8px;
	font-style: normal;
	font-family: Consolas, monospace;
	font-size: 11px;
	color: #777;
	word-break: break-word;
}

@media (max-width: 980px) {
	.dashboard-lower-grid {
		grid-template-columns: 1fr;
	}

	.dashboard-hero {
		align-items: flex-start;
	}
}

@media (max-width: 560px) {
	.dashboard-hero {
		flex-direction: column;
	}

	.dashboard-ring {
		width: 96px;
		height: 96px;
	}

	.dashboard-ring > div {
		width: 68px;
		height: 68px;
	}
}
</style>
