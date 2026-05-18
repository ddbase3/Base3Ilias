#!/bin/bash

cd "$(dirname "$0")" || exit 1

LOG='/var/log/base3-worker.log'

while true
do
	echo "[$(date '+%Y-%m-%d %H:%M:%S')] start masterworker" >> "$LOG"

	php worker.php >> "$LOG" 2>&1

	STATUS=$?

	echo "[$(date '+%Y-%m-%d %H:%M:%S')] done masterworker status=${STATUS}" >> "$LOG"

	sleep 1
done
