#!/usr/bin/env python3
"""
closet-archive Firebase → sales.json 同期スクリプト
日報処理後に自動実行し、買取成約数・失注数・来客数を更新する

使い方:
  python3 sync_archive.py              # 全データ同期
  python3 sync_archive.py --month 2026-04  # 特定月のみ
  python3 sync_archive.py --dry-run    # 確認のみ（書き込まない）
"""

import json
import sys
import os
import argparse
from datetime import datetime
try:
    import urllib.request as urllib_request
except ImportError:
    import urllib as urllib_request

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_FILE = os.path.join(SCRIPT_DIR, 'data', 'sales.json')

FIREBASE_URL = "https://closet-archive-f79d3-default-rtdb.asia-southeast1.firebasedatabase.app"

def fetch_firebase(path):
    """Firebase REST APIからデータを取得"""
    url = f"{FIREBASE_URL}/{path}.json"
    try:
        with urllib_request.urlopen(url, timeout=10) as res:
            return json.loads(res.read().decode())
    except Exception as e:
        print(f"⚠️  Firebase取得エラー ({path}): {e}")
        return None

def sync_visits(target_month=None, dry_run=False):
    """visitsデータをsales.jsonに同期"""

    # Firebase から visits 取得
    print("🔄 closet-archiveからデータ取得中...")
    visits_raw = fetch_firebase("visits")
    if not visits_raw:
        print("❌ データ取得失敗")
        return False

    # 日付別に集計
    by_date = {}
    for cust_id, cust_visits in visits_raw.items():
        if not isinstance(cust_visits, dict):
            continue
        for visit_id, v in cust_visits.items():
            if not isinstance(v, dict) or not v.get('date'):
                continue
            date = v['date']  # 例: "2026-04-15"
            if target_month and not date.startswith(target_month):
                continue

            if date not in by_date:
                by_date[date] = {
                    'total': 0, 'success': 0, 'fail': 0,
                    'no_result': 0, 'buy_amount': 0,
                    'new_count': 0, 'repeat_count': 0
                }
            by_date[date]['total'] += 1
            result = v.get('result', '')
            success_reasons = v.get('successReasons', []) or []
            if result == '成立':
                by_date[date]['success'] += 1
                by_date[date]['buy_amount'] += v.get('buyAmount', 0) or 0
                if '新規' in success_reasons:
                    by_date[date]['new_count'] += 1
                if 'リピート' in success_reasons:
                    by_date[date]['repeat_count'] += 1
            elif result == '不成立':
                by_date[date]['fail'] += 1
            else:
                by_date[date]['no_result'] += 1

    if not by_date:
        print("ℹ️  該当データなし")
        return True

    # sales.json 更新
    with open(DATA_FILE, 'r', encoding='utf-8') as f:
        data = json.load(f)

    updated = 0
    skipped = 0

    for date_str, stats in sorted(by_date.items()):
        year_month = date_str[:7]
        day_key = str(int(date_str[8:]))

        if year_month not in data['months']:
            skipped += 1
            continue
        if day_key not in data['months'][year_month]['days']:
            skipped += 1
            continue

        day = data['months'][year_month]['days'][day_key]
        day['buy_count'] = stats['success']
        day['buy_fail_count'] = stats['fail']
        day['buy_visitors'] = stats['total']
        day['buy_new_count'] = stats['new_count']
        day['buy_repeat_count'] = stats['repeat_count']
        if stats['buy_amount'] > 0:
            day['purchase_amount'] = stats['buy_amount']

        nr = f" 新規{stats['new_count']}" if stats['new_count'] else ""
        rp = f" リピート{stats['repeat_count']}" if stats['repeat_count'] else ""
        print(f"  ✓ {date_str}: 来客{stats['total']} 成約{stats['success']} 失注{stats['fail']}{nr}{rp} ¥{stats['buy_amount']:,}")
        updated += 1

    if dry_run:
        print(f"\n--dry-run: {updated}件を確認（書き込まず）")
        return True

    data['meta']['last_updated'] = datetime.now().strftime('%Y-%m-%d')
    with open(DATA_FILE, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

    print(f"\n✅ {updated}件更新 / {skipped}件スキップ")
    return True

def main():
    parser = argparse.ArgumentParser(description='closet-archive 同期')
    parser.add_argument('--month', help='対象月 (例: 2026-04)')
    parser.add_argument('--dry-run', action='store_true', help='確認のみ')
    args = parser.parse_args()

    success = sync_visits(target_month=args.month, dry_run=args.dry_run)
    sys.exit(0 if success else 1)

if __name__ == '__main__':
    main()
