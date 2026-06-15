<div class="base3ilias-log">
	<h3>ILIAS Log</h3>

	<div class="log-meta">
		<div><strong>Quelle:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['logPath']); ?></span></div>
		<div><strong>Letztes Update:</strong> <span id="ilias-log-lastupdate" class="mono">–</span></div>
	</div>

	<div class="log-actions">
		<label class="log-num">
			Einträge:
			<input type="number" id="ilias-log-num" min="1" max="<?php echo (int)$this->_['maxNum']; ?>" value="<?php echo (int)$this->_['defaultNum']; ?>" onchange="iliasLogRefresh(true)">
		</label>

		<button type="button" onclick="iliasLogRefresh(true)">Jetzt aktualisieren</button>

		<label class="log-autorefresh">
			<input type="checkbox" id="ilias-log-autorefresh" checked onchange="iliasLogToggleAutoRefresh()">
			Auto-Refresh (3s)
		</label>

		<label id="ilias-log-loading">Bitte warten…</label>
	</div>

	<div id="ilias-log-message" class="log-message" style="display: none;"></div>

	<div class="log-tablewrap">
		<table class="log-table">
			<thead>
				<tr>
					<th>Zeit</th>
					<th>Request</th>
					<th>Channel</th>
					<th>Level</th>
					<th>Log</th>
				</tr>
			</thead>
			<tbody id="ilias-log-body">
				<tr><td colspan="5" class="log-muted">–</td></tr>
			</tbody>
		</table>
	</div>
</div>

<style>
.base3ilias-log {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.base3ilias-log h3 {
	margin-top: 0;
	margin-bottom: 12px;
	font-size: 1.1em;
}

.log-meta {
	margin-bottom: 12px;
	font-size: 13px;
	color: #555;
	display: flex;
	gap: 18px;
	flex-wrap: wrap;
}

.mono {
	font-family: Consolas, monospace;
}

.log-actions {
	display: flex;
	gap: 10px;
	align-items: center;
	margin-bottom: 12px;
	flex-wrap: wrap;
}

.log-actions button {
	padding: 8px 16px;
	border: 1px solid #ccc;
	background: #f0f0f0;
	color: #333;
	border-radius: 4px;
	cursor: pointer;
	font-size: 14px;
	transition: background 0.2s, border-color 0.2s;
}

.log-actions button:hover {
	background: #e6e6e6;
	border-color: #bbb;
}

.log-num {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	color: #555;
}

.log-num input {
	width: 90px;
	padding: 6px 10px;
	border: 1px solid #ccc;
	border-radius: 4px;
	background: #fff;
	color: #333;
}

.log-autorefresh {
	font-size: 13px;
	color: #555;
	display: flex;
	align-items: center;
	gap: 6px;
	user-select: none;
}

#ilias-log-loading {
	display: none;
	color: #666;
	align-items: center;
	font-style: italic;
	font-size: 13px;
	gap: 6px;
	user-select: none;
}

.log-message {
	margin-bottom: 12px;
	padding: 8px 10px;
	border: 1px solid #e3c07a;
	background: #fffaf0;
	color: #8a5a00;
	border-radius: 4px;
	font-size: 13px;
}

.log-tablewrap {
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
}

.log-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.log-table th,
.log-table td {
	border-top: 1px solid #eee;
	padding: 8px 10px;
	vertical-align: top;
	text-align: left;
}

.log-table thead th {
	border-top: 0;
	border-bottom: 1px solid #ddd;
	font-weight: bold;
	white-space: nowrap;
}

.log-muted {
	color: #777;
	font-style: italic;
}

.log-pill {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid #ccc;
	background: #f6f6f6;
	font-size: 12px;
	white-space: nowrap;
}

.log-pill.info { border-color: #8d8; background: #f6fff6; color: #2d6a2d; }
.log-pill.notice { border-color: #9cd; background: #f3fbff; color: #135a7a; }
.log-pill.warning { border-color: #e3c07a; background: #fffaf0; color: #8a5a00; }
.log-pill.error, .log-pill.critical, .log-pill.alert, .log-pill.emergency { border-color: #d88; background: #fff5f5; color: #a33; }
.log-pill.debug { border-color: #ccc; background: #f6f6f6; color: #555; }

.log-cell-mono {
	font-family: Consolas, monospace;
	white-space: nowrap;
}

.log-cell-wrap {
	white-space: normal;
	word-break: break-word;
}
</style>

<script>
	const ILIAS_LOG_ENDPOINT = <?php echo json_encode((string)$this->_['endpoint']); ?>;

	let iliasLogTimer = null;

	function iliasLogSetLoading(state) {
		document.getElementById("ilias-log-loading").style.display = state ? "flex" : "none";
	}

	function iliasLogSetMessage(message) {
		const el = document.getElementById("ilias-log-message");
		const text = String(message || "");

		if (text === "") {
			el.style.display = "none";
			el.textContent = "";
			return;
		}

		el.style.display = "block";
		el.textContent = text;
	}

	function iliasLogEsc(s) {
		const div = document.createElement("div");
		div.textContent = String(s ?? "");
		return div.innerHTML;
	}

	function iliasLogLevelPill(level) {
		const l = String(level || "").toLowerCase();

		if (!l) {
			return '<span class="log-muted">–</span>';
		}

		const cls = "log-pill " + l;
		return '<span class="' + cls + '">' + iliasLogEsc(l) + '</span>';
	}

	function iliasLogRenderRows(rows) {
		const body = document.getElementById("ilias-log-body");

		if (!rows || rows.length === 0) {
			body.innerHTML = '<tr><td colspan="5" class="log-muted">Keine Logs gefunden.</td></tr>';
			return;
		}

		let html = "";
		for (const r of rows) {
			const ts = r.timestamp || "–";
			const req = r.request || "–";
			const channel = r.channel || "–";
			const lvl = r.level || "";
			const msg = r.message || "";

			html += "<tr>" +
				'<td class="log-cell-mono" title="' + iliasLogEsc(ts) + '">' + iliasLogEsc(ts) + "</td>" +
				'<td class="log-cell-mono">' + iliasLogEsc(req) + "</td>" +
				'<td class="log-cell-mono">' + iliasLogEsc(channel) + "</td>" +
				"<td>" + iliasLogLevelPill(lvl) + "</td>" +
				'<td class="log-cell-wrap">' + iliasLogEsc(msg) + "</td>" +
			"</tr>";
		}

		body.innerHTML = html;
	}

	async function iliasLogRefresh(force = false) {
		iliasLogSetLoading(true);

		try {
			const num = document.getElementById("ilias-log-num").value || "<?php echo (int)$this->_['defaultNum']; ?>";

			const url = new URL(ILIAS_LOG_ENDPOINT + "tail", window.location.href);
			url.searchParams.set("num", num);

			const response = await fetch(url.toString(), {
				method: "GET",
				headers: { "Accept": "application/json" }
			});

			const text = await response.text();
			let json;

			try {
				json = JSON.parse(text);
			} catch (e) {
				iliasLogSetMessage("Die Antwort konnte nicht als JSON gelesen werden.");
				iliasLogSetLoading(false);
				return;
			}

			if (json.status !== "ok") {
				iliasLogSetMessage(json.message || "Das Log konnte nicht geladen werden.");
				iliasLogSetLoading(false);
				return;
			}

			document.getElementById("ilias-log-lastupdate").textContent = json.timestamp || "–";

			if (json.data && json.data.message) {
				iliasLogSetMessage(json.data.message);
			} else {
				iliasLogSetMessage("");
			}

			iliasLogRenderRows((json.data && json.data.logs) ? json.data.logs : []);

		} catch (err) {
			iliasLogSetMessage("Das Log konnte nicht geladen werden.");
		}

		iliasLogSetLoading(false);
	}

	function iliasLogToggleAutoRefresh() {
		const enabled = document.getElementById("ilias-log-autorefresh").checked;

		if (iliasLogTimer) {
			clearInterval(iliasLogTimer);
			iliasLogTimer = null;
		}

		if (enabled) {
			iliasLogTimer = setInterval(() => iliasLogRefresh(false), 3000);
		}
	}

	iliasLogToggleAutoRefresh();
	iliasLogRefresh(true);
</script>
