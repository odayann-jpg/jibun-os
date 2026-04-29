#!/usr/bin/env bash
# Give & Take マネジメント LP → Xserver へ FTP アップロード
# 使い方: bash projects/give-take-management/deploy.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

if [[ ! -f .ftpconfig ]]; then
  echo "❌ .ftpconfig が見つかりません" >&2
  echo "   .ftpconfig.example をコピーして値を埋めてください:" >&2
  echo "   cp .ftpconfig.example .ftpconfig" >&2
  exit 1
fi

# shellcheck source=/dev/null
source .ftpconfig
: "${FTP_HOST:?FTP_HOST 未設定}"
: "${FTP_USER:?FTP_USER 未設定}"
: "${FTP_PASS:?FTP_PASS 未設定}"
: "${FTP_REMOTE:?FTP_REMOTE 未設定}"

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

# index.html
upload "$ROOT/index.html" "index.html"

# images/ 配下を全部
if [[ -d "$ROOT/images" ]]; then
  for f in "$ROOT/images"/*; do
    [[ -f "$f" ]] || continue
    fname=$(basename "$f")
    [[ "$fname" == ".DS_Store" ]] && continue
    upload "$f" "images/$fname"
  done
fi

echo ""
echo "✅ アップロード完了"
echo "   ${PUBLIC_URL:-https://giveandtake-japan.org/}"
