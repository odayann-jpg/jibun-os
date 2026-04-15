#!/bin/bash
# スタクロ ダッシュボード 一括更新スクリプト
# 使い方: ./update.sh
#         ./update.sh "コミットメッセージ"

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

ICLOUD_BACKUP_DIR="$HOME/Library/Mobile Documents/com~apple~CloudDocs/スタクロ_バックアップ"

echo "🔄 ダッシュボード更新中..."

# 1. generate_dashboard.py を実行
python3 generate_dashboard.py

# 2. iCloud に sales.json をバックアップ
mkdir -p "$ICLOUD_BACKUP_DIR"
cp data/sales.json "$ICLOUD_BACKUP_DIR/sales_$(date +%Y-%m-%d).json"
# 最新版も上書き保存
cp data/sales.json "$ICLOUD_BACKUP_DIR/sales_latest.json"
echo "💾 iCloudにバックアップ: sales_$(date +%Y-%m-%d).json"

# 3. git add & commit & push
git add dashboard.html
if git diff --cached --quiet; then
    echo "ℹ️  dashboard.html に変更なし。スキップ。"
else
    MSG="${1:-ダッシュボード更新: $(date +%Y-%m-%d)}"
    git commit -m "$MSG"
    git push origin main
    echo "✅ GitHubにプッシュ完了"
fi

echo ""
echo "📊 ローカルで確認: open $SCRIPT_DIR/dashboard.html"
echo "🌐 GitHub Pages: https://odayann-jpg.github.io/jibun-os/projects/stylist-closet/dashboard.html"
echo "   ※ Pages反映まで1〜3分かかります"
