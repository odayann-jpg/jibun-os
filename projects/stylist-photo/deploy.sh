#!/usr/bin/env bash
# スタイリストフォト → Xserver(fashion-stylist.co.jp/closet/photo/) へFTPアップロード
# 使い方: bash deploy.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

if [[ ! -f .ftpconfig ]]; then
  echo "❌ .ftpconfig が見つかりません" >&2
  exit 1
fi

# shellcheck source=/dev/null
source .ftpconfig
: "${FTP_HOST:?FTP_HOST 未設定}"
: "${FTP_USER:?FTP_USER 未設定}"
: "${FTP_PASS:?FTP_PASS 未設定}"
: "${FTP_REMOTE:?FTP_REMOTE 未設定}"

LOCAL_DIR="$ROOT/site"
if [[ ! -d "$LOCAL_DIR" ]]; then
  echo "❌ $LOCAL_DIR が見つかりません" >&2
  exit 1
fi

REMOTE_BASE="ftp://${FTP_HOST}${FTP_REMOTE}"

echo "📤 ${FTP_HOST}${FTP_REMOTE} へアップロード中..."

upload() {
  local src="$1" rel="$2"
  echo "   → ${rel}"
  curl -s -S --fail -T "$src" \
    "${REMOTE_BASE}${rel}" \
    --user "${FTP_USER}:${FTP_PASS}" \
    --ftp-create-dirs
}

# ルートファイル（index.html, styles.css）
upload "$LOCAL_DIR/index.html" "index.html"
upload "$LOCAL_DIR/styles.css" "styles.css"

# assets/images/ 配下を全部
if compgen -G "$LOCAL_DIR/assets/images/*" > /dev/null; then
  for f in "$LOCAL_DIR"/assets/images/*; do
    [[ -f "$f" ]] || continue
    fname=$(basename "$f")
    [[ "$fname" == ".DS_Store" ]] && continue
    upload "$f" "assets/images/$fname"
  done
fi

echo ""
echo "✅ アップロード完了"
echo "   ${PUBLIC_URL:-https://fashion-stylist.co.jp/closet/photo/}"
