#!/bin/bash
# 自分OS 毎日自動バックアップスクリプト
# macOS launchd から呼び出される

REPO_DIR="/Users/hirotaseiji/Desktop/自分OS"
LOG_FILE="$REPO_DIR/scripts/backup.log"
DATE=$(date '+%Y-%m-%d %H:%M')

cd "$REPO_DIR" || exit 1

# 変更がなければスキップ
if git diff --quiet && git diff --staged --quiet && [ -z "$(git ls-files --others --exclude-standard)" ]; then
    echo "[$DATE] 変更なし、スキップ" >> "$LOG_FILE"
    exit 0
fi

git add -A
git commit -m "自動バックアップ: $DATE"
git push origin main >> "$LOG_FILE" 2>&1

if [ $? -eq 0 ]; then
    echo "[$DATE] バックアップ成功" >> "$LOG_FILE"
else
    echo "[$DATE] バックアップ失敗（pushエラー）" >> "$LOG_FILE"
fi
