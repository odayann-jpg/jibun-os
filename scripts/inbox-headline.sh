#!/bin/bash
# Claude Code 起動時に inbox の状態を頭出しするスクリプト
# settings.json の SessionStart hook から呼ばれる

INBOX_DIR="/Users/hirotaseiji/Desktop/自分OS/company/secretary/inbox"
TODAY=$(date +%Y-%m-%d)

echo "【inbox 状態】"

if [ ! -d "$INBOX_DIR" ]; then
  echo "  inbox ディレクトリなし"
  exit 0
fi

# 直近 7 日間のファイル（更新時刻ベース）
RECENT=$(find "$INBOX_DIR" -name "*.md" -type f -mtime -7 2>/dev/null | sort -r)

if [ -z "$RECENT" ]; then
  echo "  直近 7 日間の inbox なし"
  exit 0
fi

echo "$RECENT" | while read file; do
  fname=$(basename "$file" .md)
  lines=$(wc -l < "$file" | tr -d ' ')
  if [ "$fname" = "$TODAY" ]; then
    echo "  ★ $fname.md ($lines 行) ← 今日"
  else
    echo "  - $fname.md ($lines 行)"
  fi
done

echo ""
echo "  → 会話開始時に今日の inbox を必ず読み、未処理の項目があれば誠司に頭出しすること"
