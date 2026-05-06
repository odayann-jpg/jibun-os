#!/bin/bash
# Notion: Googleカレンダー（西岡慎也）から「コーディネート」「同行」予定を取得して Notion DB に反映
# 毎朝 8:01 に LaunchAgent（scripts/com.jibun_os.notion-coordinate-sync.plist）から起動想定
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
# Homebrew / ユーザーPATH（launchd では狭いため）
export PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:$PATH"
LOG_DIR="$ROOT/tasks/.gcal-oauth"
mkdir -p "$LOG_DIR"
echo "===== $(date '+%Y-%m-%d %H:%M:%S %z')  sync-coordinate-notion =====" >>"$LOG_DIR/sync-notion.log"
python3 "$ROOT/sync_coordinate_to_notion.py" >>"$LOG_DIR/sync-notion.log" 2>&1
