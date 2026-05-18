#!/bin/bash

URL='https://ddahme.qualitus.net/reporting10/ilias.php?baseClass=ilUIPluginRouterGUI&cmdClass=ilBase3IliasAdapterAjaxGUI&cmd=dispatch&name=masterworker'
LOG='/var/log/base3-worker.log'

while true
do
	echo "[$(date '+%Y-%m-%d %H:%M:%S')] start masterworker" >> "$LOG"

	curl -k -fsS \
		--connect-timeout 10 \
		--max-time 0 \
		"$URL" >> "$LOG" 2>&1

	STATUS=$?

	echo "[$(date '+%Y-%m-%d %H:%M:%S')] done masterworker status=${STATUS}" >> "$LOG"

	sleep 1
done
