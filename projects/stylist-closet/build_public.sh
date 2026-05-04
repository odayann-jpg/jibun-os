#!/bin/bash
# public/ を更新して FTP に自動アップロード
# 使い方: bash build_public.sh

set -e

mkdir -p public/images

cp kaitori.html public/index.html

if ls images/* 1> /dev/null 2>&1; then
  # 直下ファイルのみコピー（hero-drafts のような作業用サブディレクトリは除外）
  find images -maxdepth 1 -type f -exec cp {} public/images/ \;
fi

echo "✓ public/ を更新しました。"

# FTP設定を読み込む
if [ ! -f ".ftpconfig" ]; then
  echo "ℹ️  .ftpconfig がないためFTPスキップ。手動でアップ: $(pwd)/public/"
  exit 0
fi

source .ftpconfig

echo ""
echo "📤 FTPアップロード中... ($FTP_HOST)"

# index.html をアップロード
curl -s -T public/index.html \
  "ftp://$FTP_HOST/$FTP_REMOTE" \
  --user "$FTP_USER:$FTP_PASS" \
  --ftp-create-dirs

# images/ 以下を全てアップロード
for f in public/images/*; do
  fname=$(basename "$f")
  echo "   → images/$fname"
  curl -s -T "$f" \
    "ftp://$FTP_HOST/${FTP_REMOTE}images/" \
    --user "$FTP_USER:$FTP_PASS" \
    --ftp-create-dirs
done

echo ""
echo "✅ FTPアップロード完了！"
echo "   https://fashion-stylist.co.jp/closet/"
