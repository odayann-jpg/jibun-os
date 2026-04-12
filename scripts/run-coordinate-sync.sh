#!/bin/bash
# MMS: Googleカレンダーから「コーディネート」予定のみ取得して Firebase(MMS) に反映
# 毎朝8時は LaunchAgent（scripts/com.jibun_os.mms-coordinate-sync.plist）から起動想定
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
# Homebrew / ユーザーPATH（launchd では狭いため）
export PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:$PATH"
LOG_DIR="$ROOT/tasks/.gcal-oauth"
mkdir -p "$LOG_DIR"
echo "===== $(date '+%Y-%m-%d %H:%M:%S %z')  sync-coordinate =====" >>"$LOG_DIR/sync.log"
python3 "$ROOT/sync_mms.py" sync-coordinate >>"$LOG_DIR/sync.log" 2>&1
