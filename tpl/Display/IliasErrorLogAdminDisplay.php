<div class="base3ilias-errorlog">
	<h3>ILIAS Error Logs</h3>

	<div class="errorlog-meta">
		<div><strong>Quelle:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['errorPath']); ?></span></div>
		<div><strong>Letztes Update:</strong> <span id="errorlog-lastupdate" class="mono">–</span></div>
	</div>

	<div class="errorlog-actions">
		<label class="errorlog-num">
			Dateien:
			<input type="number" id="errorlog-num" min="1" max="<?php echo (int)$this->_['maxFiles']; ?>" value="<?php echo (int)$this->_['defaultNum']; ?>" onchange="errorLogRefresh(true)">
		</label>

		<button type="button" onclick="errorLogRefresh(true)">Jetzt aktualisieren</button>

		<label class="errorlog-autorefresh">
			<input type="checkbox" id="errorlog-autorefresh" checked onchange="errorLogToggleAutoRefresh()">
			Auto-Refresh (3s)
		</label>

		<label id="errorlog-loading">Bitte warten…</label>
	</div>

	<div id="errorlog-message" class="errorlog-message" style="display: none;"></div>

	<div class="errorlog-tablewrap">
		<table class="errorlog-table">
			<thead>
				<tr>
					<th>Datei</th>
					<th>Größe</th>
					<th>Geändert</th>
					<th>Lesbar</th>
				</tr>
			</thead>
			<tbody id="errorlog-files-body">
				<tr><td colspan="4" class="errorlog-muted">–</td></tr>
			</tbody>
		</table>
	</div>
</div>

<style>
.base3ilias-errorlog {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.base3ilias-errorlog h3 {
	margin-top: 0;
	margin-bottom: 12px;
	font-size: 1.1em;
}

.errorlog-meta {
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

.errorlog-actions {
	display: flex;
	gap: 10px;
	align-items: center;
	margin-bottom: 12px;
	flex-wrap: wrap;
}

.errorlog-actions button {
	padding: 8px 16px;
	border: 1px solid #ccc;
	background: #f0f0f0;
	color: #333;
	border-radius: 4px;
	cursor: pointer;
	font-size: 14px;
	transition: background 0.2s, border-color 0.2s;
}

.errorlog-actions button:hover {
	background: #e6e6e6;
	border-color: #bbb;
}

.errorlog-num {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	color: #555;
}

.errorlog-num input {
	width: 90px;
	padding: 6px 10px;
	border: 1px solid #ccc;
	border-radius: 4px;
	background: #fff;
	color: #333;
}

.errorlog-autorefresh {
	font-size: 13px;
	color: #555;
	display: flex;
	align-items: center;
	gap: 6px;
	user-select: none;
}

#errorlog-loading {
	display: none;
	color: #666;
	align-items: center;
	font-style: italic;
	font-size: 13px;
	gap: 6px;
	user-select: none;
}

.errorlog-message {
	margin-bottom: 12px;
	padding: 8px 10px;
	border: 1px solid #e3c07a;
	background: #fffaf0;
	color: #8a5a00;
	border-radius: 4px;
	font-size: 13px;
}

.errorlog-tablewrap {
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
}

.errorlog-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.errorlog-table th,
.errorlog-table td {
	border-top: 1px solid #eee;
	padding: 8px 10px;
	vertical-align: top;
	text-align: left;
}

.errorlog-table thead th {
	border-top: 0;
	border-bottom: 1px solid #ddd;
	font-weight: bold;
	white-space: nowrap;
}

.errorlog-muted {
	color: #777;
	font-style: italic;
}

.errorlog-pill {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid #ccc;
	background: #f6f6f6;
	font-size: 12px;
	white-space: nowrap;
}

.errorlog-pill.info {
	border-color: #9cd;
	background: #f3fbff;
	color: #135a7a;
}

.errorlog-pill.warning {
	border-color: #e3c07a;
	background: #fffaf0;
	color: #8a5a00;
}

.errorlog-cell-mono {
	font-family: Consolas, monospace;
	white-space: nowrap;
}

.errorlog-cell-wrap {
	white-space: normal;
	word-break: break-word;
}

.errorlog-file-row {
	cursor: pointer;
}

.errorlog-file-row:hover td {
	background: #fafafa;
}

.errorlog-file-row.active td {
	background: #f3fbff;
}

.errorlog-file-name {
	color: #135a7a;
	font-family: Consolas, monospace;
}

.errorlog-file-row:hover .errorlog-file-name {
	text-decoration: underline;
}

.errorlog-expanded-row td {
	background: #fafafa;
	cursor: default;
}

.errorlog-content-head {
	display: flex;
	gap: 12px;
	flex-wrap: wrap;
	font-size: 12px;
	color: #666;
	margin-bottom: 8px;
}

.errorlog-content-box {
	max-height: 420px;
	overflow: auto;
	border: 1px solid #ddd;
	background: #fff;
	border-radius: 4px;
	padding: 10px;
}

.errorlog-content-box pre {
	margin: 0;
	font-family: Consolas, monospace;
	font-size: 12px;
	white-space: pre-wrap;
	word-break: break-word;
	color: #333;
}

.errorlog-content-warning {
	margin-bottom: 8px;
	padding: 6px 8px;
	border: 1px solid #e3c07a;
	background: #fffaf0;
	color: #8a5a00;
	border-radius: 4px;
	font-size: 12px;
}
</style>

<script>
	const ERRORLOG_ENDPOINT = <?php echo json_encode((string)$this->_['endpoint']); ?>;

	let errorLogTimer = null;
	let errorLogFiles = [];
	let errorLogExpandedFile = "";
	let errorLogFileCache = {};

	function errorLogSetLoading(state) {
		document.getElementById("errorlog-loading").style.display = state ? "flex" : "none";
	}

	function errorLogSetMessage(message) {
		const el = document.getElementById("errorlog-message");
		const text = String(message || "");

		if (text === "") {
			el.style.display = "none";
			el.textContent = "";
			return;
		}

		el.style.display = "block";
		el.textContent = text;
	}

	function errorLogEsc(s) {
		const div = document.createElement("div");
		div.textContent = String(s ?? "");
		return div.innerHTML;
	}

	function errorLogReadablePill(readable) {
		if (readable) {
			return '<span class="errorlog-pill info">yes</span>';
		}

		return '<span class="errorlog-pill warning">no</span>';
	}

	function errorLogStopAutoRefresh() {
		const checkbox = document.getElementById("errorlog-autorefresh");
		checkbox.checked = false;

		if (errorLogTimer) {
			clearInterval(errorLogTimer);
			errorLogTimer = null;
		}
	}

	function errorLogRenderFiles(files) {
		errorLogFiles = files || [];

		const body = document.getElementById("errorlog-files-body");

		if (errorLogFiles.length === 0) {
			body.innerHTML = '<tr><td colspan="4" class="errorlog-muted">Keine Error-Dateien gefunden.</td></tr>';
			return;
		}

		let expandedExists = false;
		let html = "";

		for (let i = 0; i < errorLogFiles.length; i++) {
			const f = errorLogFiles[i];
			const name = f.name || "";
			const size = f.size_formatted || "0 B";
			const mtime = f.mtime || "–";
			const readable = !!f.readable;
			const expanded = errorLogExpandedFile !== "" && errorLogExpandedFile === name;

			if (expanded) {
				expandedExists = true;
			}

			html += '<tr class="errorlog-file-row' + (expanded ? " active" : "") + '" onclick="errorLogToggleFile(' + i + ')">' +
				'<td><span class="errorlog-file-name">' + errorLogEsc(name) + "</span></td>" +
				'<td class="errorlog-cell-mono">' + errorLogEsc(size) + "</td>" +
				'<td class="errorlog-cell-mono">' + errorLogEsc(mtime) + "</td>" +
				"<td>" + errorLogReadablePill(readable) + "</td>" +
			"</tr>";

			if (expanded) {
				html += errorLogRenderExpandedRow(name);
			}
		}

		if (!expandedExists) {
			errorLogExpandedFile = "";
		}

		body.innerHTML = html;
	}

	function errorLogRenderExpandedRow(file) {
		const cached = errorLogFileCache[file];

		if (!cached || cached.loading) {
			return '<tr class="errorlog-expanded-row" onclick="event.stopPropagation()"><td colspan="4"><div class="errorlog-muted">Lade Datei…</div></td></tr>';
		}

		if (cached.error) {
			return '<tr class="errorlog-expanded-row" onclick="event.stopPropagation()"><td colspan="4"><div class="errorlog-message">' + errorLogEsc(cached.error) + '</div></td></tr>';
		}

		const data = cached.data || {};
		const content = data.content || "";
		const truncated = !!data.truncated;

		let warning = "";
		if (truncated) {
			warning = '<div class="errorlog-content-warning">Die Datei ist größer als der Anzeigebereich. Es wird nur das Dateiende angezeigt.</div>';
		}

		return '<tr class="errorlog-expanded-row" onclick="event.stopPropagation()"><td colspan="4">' +
			'<div class="errorlog-content-head">' +
				'<span><strong>Datei:</strong> <span class="mono">' + errorLogEsc(data.file || file) + '</span></span>' +
				'<span><strong>Größe:</strong> <span class="mono">' + errorLogEsc(data.size_formatted || "–") + '</span></span>' +
				'<span><strong>Gelesen:</strong> <span class="mono">' + errorLogEsc(data.read_bytes_formatted || "–") + '</span></span>' +
				'<span><strong>Geändert:</strong> <span class="mono">' + errorLogEsc(data.mtime || "–") + '</span></span>' +
			'</div>' +
			warning +
			'<div class="errorlog-content-box"><pre>' + errorLogEsc(content || "–") + '</pre></div>' +
		'</td></tr>';
	}

	function errorLogToggleFile(index) {
		const file = errorLogFiles[index] && errorLogFiles[index].name ? errorLogFiles[index].name : "";

		if (file === "") {
			return;
		}

		if (errorLogExpandedFile === file) {
			errorLogExpandedFile = "";
			errorLogRenderFiles(errorLogFiles);
			return;
		}

		errorLogExpandedFile = file;
		errorLogStopAutoRefresh();

		if (!errorLogFileCache[file]) {
			errorLogFileCache[file] = { loading: true };
			errorLogRenderFiles(errorLogFiles);
			errorLogLoadFile(file);
			return;
		}

		errorLogRenderFiles(errorLogFiles);
	}

	async function errorLogLoadFile(file) {
		try {
			const url = new URL(ERRORLOG_ENDPOINT + "read", window.location.href);
			url.searchParams.set("file", file);

			const response = await fetch(url.toString(), {
				method: "GET",
				headers: { "Accept": "application/json" }
			});

			const text = await response.text();
			let json;

			try {
				json = JSON.parse(text);
			} catch (e) {
				errorLogFileCache[file] = { error: "Die Antwort konnte nicht als JSON gelesen werden." };
				errorLogRenderFiles(errorLogFiles);
				return;
			}

			if (json.status !== "ok") {
				errorLogFileCache[file] = { error: json.message || "Die Datei konnte nicht geladen werden." };
				errorLogRenderFiles(errorLogFiles);
				return;
			}

			if (json.data && json.data.message) {
				errorLogFileCache[file] = { error: json.data.message };
				errorLogRenderFiles(errorLogFiles);
				return;
			}

			errorLogFileCache[file] = { data: json.data || {} };
			errorLogRenderFiles(errorLogFiles);
		} catch (err) {
			errorLogFileCache[file] = { error: "Die Datei konnte nicht geladen werden." };
			errorLogRenderFiles(errorLogFiles);
		}
	}

	async function errorLogRefresh(force = false) {
		errorLogSetLoading(true);

		try {
			const num = document.getElementById("errorlog-num").value || "<?php echo (int)$this->_['defaultNum']; ?>";

			const url = new URL(ERRORLOG_ENDPOINT + "list", window.location.href);
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
				errorLogSetMessage("Die Antwort konnte nicht als JSON gelesen werden.");
				errorLogSetLoading(false);
				return;
			}

			if (json.status !== "ok") {
				errorLogSetMessage(json.message || "Die Error-Dateien konnten nicht geladen werden.");
				errorLogSetLoading(false);
				return;
			}

			document.getElementById("errorlog-lastupdate").textContent = json.timestamp || "–";

			if (json.data && json.data.message) {
				errorLogSetMessage(json.data.message);
			} else {
				errorLogSetMessage("");
			}

			errorLogRenderFiles((json.data && json.data.files) ? json.data.files : []);
		} catch (err) {
			errorLogSetMessage("Die Error-Dateien konnten nicht geladen werden.");
		}

		errorLogSetLoading(false);
	}

	function errorLogToggleAutoRefresh() {
		const checkbox = document.getElementById("errorlog-autorefresh");
		const enabled = checkbox.checked;

		if (errorLogTimer) {
			clearInterval(errorLogTimer);
			errorLogTimer = null;
		}

		if (enabled && errorLogExpandedFile !== "") {
			checkbox.checked = false;
			return;
		}

		if (enabled) {
			errorLogTimer = setInterval(() => errorLogRefresh(false), 3000);
		}
	}

	errorLogToggleAutoRefresh();
	errorLogRefresh(true);
</script>
