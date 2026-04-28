#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CRM Daily Sync
- macOSキーチェーンから認証情報を取得
- CRMにログインして全顧客のリースアイテムCSVを取得
- 前回との差分を検出してログに記録
- ローカルのpurchasesフォルダを最新化

使い方:
  python3 sync.py            # 同期実行
  python3 sync.py --dry-run  # 接続テストのみ
"""

import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import os
import sys
import csv
import json
import time
import logging
import subprocess
from datetime import datetime
from pathlib import Path

# ========== 設定 ==========
BASE_DIR = Path('/Users/hirotaseiji/Desktop/自分OS/projects/crm-source')
PURCHASES_DIR = BASE_DIR / 'purchases'
LOG_DIR = BASE_DIR / 'logs'
SUMMARY_DIR = BASE_DIR / 'sync-history'
MEMBERS_JSON = Path('/tmp/members.json')

CRM_URL = 'https://fashion-stylist.co.jp/crm'
KEYCHAIN_USER = 'kanri'
KEYCHAIN_SERVICE = 'fsj-crm'

# サーバー負荷軽減のためのスリープ秒
REQUEST_INTERVAL = 1.5

# ========== ログ設定 ==========
LOG_DIR.mkdir(parents=True, exist_ok=True)
SUMMARY_DIR.mkdir(parents=True, exist_ok=True)
PURCHASES_DIR.mkdir(parents=True, exist_ok=True)

today_str = datetime.now().strftime('%Y-%m-%d')
log_file = LOG_DIR / f'sync_{today_str}.log'

logging.basicConfig(
    filename=str(log_file),
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    encoding='utf-8'
)
# stdoutにも出力
console = logging.StreamHandler(sys.stdout)
console.setLevel(logging.INFO)
console.setFormatter(logging.Formatter('%(asctime)s [%(levelname)s] %(message)s'))
logging.getLogger().addHandler(console)


def get_password_from_keychain():
    """macOSキーチェーンからパスワードを取得"""
    try:
        result = subprocess.run(
            ['security', 'find-generic-password',
             '-a', KEYCHAIN_USER,
             '-s', KEYCHAIN_SERVICE,
             '-w'],
            capture_output=True,
            text=True,
            timeout=10
        )
        if result.returncode == 0:
            return result.stdout.strip()
    except Exception as e:
        logging.error(f'Keychain access failed: {e}')
    return None


def make_session(password):
    """ログイン済みセッションを作成"""
    ctx = ssl.create_default_context()
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(cj),
        urllib.request.HTTPSHandler(context=ctx)
    )
    opener.addheaders = [('User-Agent', 'Mozilla/5.0 (CRM Daily Sync)')]

    # 1. ログインページGET（CSRF cookie取得）
    url_login = f'{CRM_URL}/login/'
    opener.open(url_login, timeout=30)

    # 2. ログインPOST
    login_data = urllib.parse.urlencode({
        '_method': 'POST',
        'data[User][username]': KEYCHAIN_USER,
        'data[User][password]': password,
        'mode': 'login',
    }).encode('utf-8')
    req = urllib.request.Request(url_login, data=login_data, method='POST')
    resp = opener.open(req, timeout=30)
    body = resp.read().decode('utf-8', errors='replace')

    # ログイン成功判定
    if 'ログアウト' not in body and 'logout' not in body.lower():
        if 'login' in resp.url.lower():
            raise RuntimeError('Login failed: returned to login page')
    return opener


def fetch_member_purchases(opener, member_id):
    """指定顧客のリースアイテムCSVを取得"""
    url = f'{CRM_URL}/purchases/csv'
    data = urllib.parse.urlencode({'member_id': member_id}).encode('utf-8')
    req = urllib.request.Request(url, data=data, method='POST')
    resp = opener.open(req, timeout=60)
    raw = resp.read()
    text = raw.decode('cp932', errors='replace')
    # CSVヘッダーで判定（HTML返却時を除外）
    if not text.startswith('日付,対象契約'):
        return None
    return raw, text


def count_items(csv_text):
    """CSV行数（ヘッダー除く）を数える"""
    lines = [l for l in csv_text.split('\n') if l.strip()]
    return max(0, len(lines) - 1)


def main(dry_run=False):
    logging.info('=' * 50)
    logging.info('CRM Daily Sync 開始')
    logging.info('=' * 50)

    # パスワード取得
    password = get_password_from_keychain()
    if not password:
        logging.error('キーチェーンからパスワード取得失敗')
        sys.exit(1)
    logging.info('キーチェーンから認証情報取得OK')

    # 顧客リスト読み込み
    if not MEMBERS_JSON.exists():
        logging.error(f'顧客マスタが見つかりません: {MEMBERS_JSON}')
        sys.exit(1)

    with open(MEMBERS_JSON, encoding='utf-8') as f:
        members = json.load(f)
    logging.info(f'対象顧客: {len(members)}名')

    # ログイン
    try:
        opener = make_session(password)
        logging.info('CRMログイン成功')
    except Exception as e:
        logging.error(f'ログイン失敗: {e}')
        sys.exit(1)

    if dry_run:
        logging.info('[DRY-RUN] 接続テストのみで終了')
        return

    # 前回の点数を保存しておく（差分検出用）
    snapshot_file = SUMMARY_DIR / 'last_snapshot.json'
    last_snapshot = {}
    if snapshot_file.exists():
        try:
            with open(snapshot_file, encoding='utf-8') as f:
                last_snapshot = json.load(f)
        except Exception:
            pass

    # 各顧客のCSVを取得
    new_snapshot = {}
    changes = []
    success = 0
    no_data = 0
    errors = []

    for i, m in enumerate(members, 1):
        cid = m.get('顧客ID', '').strip()
        name = m.get('お名前', '').strip()
        if not cid or not name:
            continue

        try:
            result = fetch_member_purchases(opener, cid)
            if result is None:
                no_data += 1
                new_snapshot[cid] = {'name': name, 'count': 0}
                continue

            raw, text = result
            count = count_items(text)
            new_snapshot[cid] = {'name': name, 'count': count}

            # 保存
            safe_name = name.replace(' ', '_').replace('/', '_').replace('?', '')
            filepath = PURCHASES_DIR / f'member_{cid}_{safe_name}.csv'
            with open(filepath, 'wb') as f:
                f.write(raw)

            # 差分検出
            prev_count = last_snapshot.get(cid, {}).get('count', 0)
            if prev_count != count:
                diff = count - prev_count
                changes.append({
                    'cid': cid,
                    'name': name,
                    'prev': prev_count,
                    'now': count,
                    'diff': diff,
                })

            success += 1
            if i % 20 == 0:
                logging.info(f'  進捗 {i}/{len(members)}')
        except Exception as e:
            errors.append({'cid': cid, 'name': name, 'error': str(e)})
            logging.warning(f'  {cid} {name}: {e}')

        time.sleep(REQUEST_INTERVAL)

    # スナップショット保存
    with open(snapshot_file, 'w', encoding='utf-8') as f:
        json.dump(new_snapshot, f, ensure_ascii=False, indent=2)

    # 結果サマリ
    logging.info(f'完了: 成功 {success} / リースアイテムなし {no_data} / エラー {len(errors)}')

    if changes:
        logging.info(f'== 変更検出: {len(changes)}名 ==')
        for c in changes:
            arrow = '↑' if c['diff'] > 0 else '↓'
            logging.info(f'  {arrow} {c["name"]} (ID {c["cid"]}): {c["prev"]} → {c["now"]} ({c["diff"]:+d})')

        # 変更レポート保存
        change_report = SUMMARY_DIR / f'changes_{today_str}.json'
        with open(change_report, 'w', encoding='utf-8') as f:
            json.dump(changes, f, ensure_ascii=False, indent=2)
        logging.info(f'変更レポート: {change_report}')
    else:
        logging.info('変更なし')

    if errors:
        logging.warning(f'エラー一覧:')
        for e in errors:
            logging.warning(f'  {e["cid"]} {e["name"]}: {e["error"]}')

    logging.info('=' * 50)
    logging.info('CRM Daily Sync 完了')
    logging.info('=' * 50)


if __name__ == '__main__':
    dry_run = '--dry-run' in sys.argv
    try:
        main(dry_run=dry_run)
    except Exception as e:
        logging.error(f'同期失敗: {e}', exc_info=True)
        sys.exit(1)
