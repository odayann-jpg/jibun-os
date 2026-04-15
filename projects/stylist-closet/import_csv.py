#!/usr/bin/env python3
"""
スタクロ CSV インポートスクリプト
Googleスプレッドシートからエクスポートした CSV を sales.json に取り込む

使い方:
  python3 import_csv.py 4月.csv --month 2026-04
  python3 import_csv.py 3月.csv --month 2026-03 --overwrite

対応CSV列（スプレッドシートの列順）:
  日付, ネット+店頭売上, ネット売上点, 店頭売上, 店頭売上点, 合計売上,
  販売件数, 買取件数, 仕入れ, 買取金額, フリー, 知り合い,
  ネット1品平均価格, 平均買取申請, 平均持ち込み点, アイテム単価, 1日平均買取金額
"""

import json
import csv
import sys
import os
import argparse
from datetime import datetime

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_FILE = os.path.join(SCRIPT_DIR, 'data', 'sales.json')

WEEKDAY_JP = ['月', '火', '水', '木', '金', '土', '日']

def parse_int(value, default=0):
    """数値のパース（¥記号・カンマ対応）"""
    if not value or str(value).strip() in ('', '-', '¥0', '0'):
        return default
    cleaned = str(value).replace('¥', '').replace(',', '').replace(' ', '').strip()
    try:
        return int(float(cleaned))
    except (ValueError, TypeError):
        return default

def get_weekday(date_str):
    """日付から曜日を取得"""
    try:
        # YYYY-MM-DD or M/D or 1 (day number)
        if '-' in date_str:
            d = datetime.strptime(date_str, '%Y-%m-%d')
        elif '/' in date_str:
            parts = date_str.split('/')
            d = datetime(2026, int(parts[0]), int(parts[1]))
        else:
            return ''
        return WEEKDAY_JP[d.weekday()]
    except Exception:
        return ''

def import_csv(csv_path, year_month, overwrite=False):
    """CSV を sales.json にインポート"""
    if not os.path.exists(csv_path):
        print(f"エラー: {csv_path} が見つかりません")
        sys.exit(1)

    # sales.json を読み込み
    with open(DATA_FILE, 'r', encoding='utf-8') as f:
        data = json.load(f)

    if year_month not in data['months']:
        data['months'][year_month] = {
            'target': {'total_sales': 0, 'net_sales': 0, 'store_sales': 0, 'gross_profit_rate': 0.6, 'notes': ''},
            'days': {},
            'expenses': [],
            'summary': None
        }

    month_data = data['months'][year_month]
    year, month = year_month.split('-')

    imported = 0
    skipped = 0

    with open(csv_path, 'r', encoding='utf-8-sig') as f:
        reader = csv.reader(f)
        rows = list(reader)

    # ヘッダー行をスキップ（数字が入っていない行）
    data_rows = []
    for row in rows:
        if not row:
            continue
        # 最初の列が日付（数字）かどうかチェック
        first = str(row[0]).strip()
        if first.isdigit():
            data_rows.append(row)
        elif '/' in first:
            data_rows.append(row)

    print(f"\n{year_month} のデータを取り込みます（{len(data_rows)} 日分）\n")

    for row in data_rows:
        if len(row) < 1:
            continue

        # 日付の取得
        day_raw = str(row[0]).strip()
        if day_raw.isdigit():
            day_num = int(day_raw)
        elif '/' in day_raw:
            day_num = int(day_raw.split('/')[-1])
        else:
            continue

        day_str = str(day_num)
        date_str = f"{year}-{month}-{str(day_num).zfill(2)}"
        weekday = get_weekday(date_str)

        # 既存データチェック
        if day_str in month_data['days'] and not overwrite:
            skipped += 1
            continue

        def col(i, default=0):
            return parse_int(row[i] if i < len(row) else '', default)

        # 列マッピング（スプレッドシートの列順に対応）
        # 0: 日付
        # 1: ネット+店頭売上（合計）
        # 2: ネット売上点
        # 3: 店頭売上
        # 4: 店頭売上点
        # 5: 合計売上（重複の場合あり → col1を使用）
        # 6: 販売件数
        # 7: 買取件数
        # 8: 仕入れ
        # 9: 買取金額
        # 10: フリー
        # 11: 知り合い
        # 12: ネット1品平均価格
        # 13: 平均買取申請
        # 14: 平均持ち込み点
        # 15: アイテム単価
        # 16: 1日平均買取金額

        net_items = col(2)
        store_items = col(4)
        total_sales = col(1)
        store_sales = col(3)
        net_sales = total_sales - store_sales if total_sales >= store_sales else total_sales

        day_data = {
            'date': date_str,
            'weekday': weekday,
            'net_sales': net_sales,
            'net_items': net_items,
            'store_sales': store_sales,
            'store_items': store_items,
            'total_sales': total_sales,
            'sell_count': col(6),
            'buy_count': col(7),
            'purchase_amount': col(9),
            'purchase_people': 0,
            'items_brought': col(14),
            'free_count': col(10),
            'acquaintance_count': col(11),
            'avg_net_price': col(12),
            'avg_purchase_price': col(13),
            'avg_items_brought': col(14),
            'avg_item_price': col(15),
            'avg_daily_purchase': col(16),
            'staff': {},
            'monthly_cumulative': {},
            'highlights': '',
            'raw_report': ''
        }

        month_data['days'][day_str] = day_data
        imported += 1

    # 日付順にソート
    month_data['days'] = dict(sorted(month_data['days'].items(), key=lambda x: int(x[0])))

    # 保存
    data['meta']['last_updated'] = datetime.now().strftime('%Y-%m-%d')
    with open(DATA_FILE, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

    print(f"✅ 取り込み完了: {imported} 日分追加 / {skipped} 日分スキップ（既存）")
    if skipped > 0:
        print("   既存データを上書きするには --overwrite オプションを使ってください")
    print(f"\n次のステップ: python3 generate_dashboard.py でダッシュボードを更新")

def main():
    parser = argparse.ArgumentParser(description='スタクロ CSV インポーター')
    parser.add_argument('csv_file', help='インポートするCSVファイルのパス')
    parser.add_argument('--month', required=True, help='対象月（例: 2026-04）')
    parser.add_argument('--overwrite', action='store_true', help='既存データを上書き')
    args = parser.parse_args()

    # 月フォーマット確認
    try:
        datetime.strptime(args.month + '-01', '%Y-%m-%d')
    except ValueError:
        print(f"エラー: --month は YYYY-MM 形式で指定してください（例: 2026-04）")
        sys.exit(1)

    import_csv(args.csv_file, args.month, args.overwrite)

if __name__ == '__main__':
    main()
