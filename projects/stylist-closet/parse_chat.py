#!/usr/bin/env python3
"""
スタクロ LINE チャットログ パーサー
LINE グループの日報テキストを解析して sales.json に取り込む

使い方:
  python3 parse_chat.py chatlog.txt --month 2026-04
  python3 parse_chat.py chatlog.txt --all   # 全月自動検出
  cat chatlog.txt | python3 parse_chat.py --month 2026-03
"""

import json
import re
import os
import sys
import argparse
from datetime import datetime, date

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_FILE = os.path.join(SCRIPT_DIR, 'data', 'sales.json')

WEEKDAY_JP = ['月', '火', '水', '木', '金', '土', '日']
MONTHS_JP = {'1': '01', '2': '02', '3': '03', '4': '04', '5': '05',
             '6': '06', '7': '07', '8': '08', '9': '09', '10': '10',
             '11': '11', '12': '12'}

def clean_yen(s):
    """¥付きの数値文字列をintに変換"""
    if not s:
        return 0
    s = str(s).replace('¥', '').replace(',', '').replace(' ', '').replace('円', '').strip()
    try:
        return int(float(s))
    except:
        return 0

def parse_report_block(text, year='2026'):
    """日報テキストブロックを解析して辞書を返す"""
    result = {}

    # 日付を特定（例: "4/13(月)" or "3/31(火)"）
    date_match = re.search(r'(\d{1,2})/(\d{1,2})[（(]([月火水木金土日])[）)]', text)
    if not date_match:
        return None

    month_num = date_match.group(1)
    day_num = date_match.group(2)
    weekday = date_match.group(3)

    month_str = MONTHS_JP.get(month_num, month_num.zfill(2))
    day_str = day_num.zfill(2)
    year_month = f"{year}-{month_str}"
    date_str = f"{year}-{month_str}-{day_str}"

    result['date'] = date_str
    result['weekday'] = weekday
    result['year_month'] = year_month
    result['day_key'] = str(int(day_num))

    # ===== 本日の出品 =====
    # パターン: 菊地XX / 島野XX (休み, 白金台なども対応)
    kikuchi_match = re.search(r'菊[地池][\s　]*(\d+|休み|白金台|赤坂)', text)
    shimano_match = re.search(r'島野[\s　]*(\d+|休み|白金台|赤坂)', text)

    kikuchi_listings = 0
    shimano_listings = 0
    kikuchi_status = ''
    shimano_status = ''

    if kikuchi_match:
        val = kikuchi_match.group(1)
        if val.isdigit():
            kikuchi_listings = int(val)
        else:
            kikuchi_status = val
    if shimano_match:
        val = shimano_match.group(1)
        if val.isdigit():
            shimano_listings = int(val)
        else:
            shimano_status = val

    result['staff'] = {
        '菊池': {'listings': kikuchi_listings, 'status': kikuchi_status or ''},
        '島野': {'listings': shimano_listings, 'status': shimano_status or ''},
    }

    # ===== 月間累計 =====
    # パターン: 菊地XX島野XX累計XX or 菊地XX 島野XX 累計XX
    cumulative_match = re.search(
        r'菊[地池][\s　]*(\d+)[\s　]*島野[\s　]*(\d+)[\s　]*累計[\s　]*(\d+)',
        text
    )
    if cumulative_match:
        result['monthly_cumulative'] = {
            'staff_listings': {
                '菊池': int(cumulative_match.group(1)),
                '島野': int(cumulative_match.group(2)),
            }
        }

    # ===== 買取 =====
    buy_count = 0   # 組数（何グループ）
    buy_items = 0   # 点数（何着）
    purchase_amount = 0
    buy_section = re.search(r'[◯○]?買取[\s\S]*?(?=[◯○]|$)', text[:500])
    if buy_section:
        buy_text = buy_section.group()
        if 'なし' not in buy_text:
            items_match = re.search(r'(\d+)点', buy_text)
            groups_match = re.search(r'(\d+)[組客]', buy_text)
            amount_match = re.search(r'¥([\d,]+)', buy_text)
            if items_match:
                buy_items = int(items_match.group(1))
            if groups_match:
                buy_count = int(groups_match.group(1))
            if amount_match:
                purchase_amount = clean_yen(amount_match.group(1))

    result['buy_count'] = buy_count
    result['buy_items'] = buy_items
    result['purchase_amount'] = purchase_amount

    # ===== ネット売上 =====
    # 月間売上セクションより前のネット売上を抽出
    net_sales = 0
    net_items = 0

    # 月間売上の前の部分だけ見る
    monthly_section_start = text.find('月間売上')
    if monthly_section_start == -1:
        monthly_section_start = len(text)
    text_before_monthly = text[:monthly_section_start]

    # ネット売上セクションを探す
    net_match = re.search(
        r'[◯○]?ネット売上[\s\S]{0,300}?(\d+)点[^\d]*¥?([\d,]+)',
        text_before_monthly
    )
    if net_match:
        net_items = int(net_match.group(1))
        net_sales = clean_yen(net_match.group(2))
    else:
        # 旧フォーマット: "ネットX点売り上XXX円"
        net_match2 = re.search(r'ネット(\d+)点.*?¥?([\d,]+)', text_before_monthly)
        if net_match2:
            net_items = int(net_match2.group(1))
            net_sales = clean_yen(net_match2.group(2))

    # ネット売上が0でも "ネット売上 0点¥0" or "なし" の場合はOK
    # 個別商品から合計を計算することも可能だが複雑なのでスキップ
    result['net_sales'] = net_sales
    result['net_items'] = net_items

    # ===== 店頭売上 =====
    store_sales = 0
    store_items = 0
    store_section = re.search(r'[◯○]?店頭売上[\s\S]{0,200}?(\d+)点[^\d]*¥?([\d,]+)', text_before_monthly)
    if store_section:
        store_items = int(store_section.group(1))
        store_sales = clean_yen(store_section.group(2))

    result['store_sales'] = store_sales
    result['store_items'] = store_items
    result['total_sales'] = net_sales + store_sales

    # ===== 月間売上累計 =====
    # パターン: ネット ¥XXX(XX点) 店頭 ¥XXX 総合 ¥XXX
    monthly_net_match = re.search(r'ネット[\s\n]*¥?([\d,]+)[\s　]*[\(（]?(\d+)点', text[monthly_section_start:])
    monthly_store_match = re.search(r'店頭[\s\n]*¥?([\d,]+)', text[monthly_section_start:])
    monthly_total_match = re.search(r'総合[\s\n]*¥?([\d,]+)', text[monthly_section_start:])

    monthly_cumulative = result.get('monthly_cumulative', {})
    if monthly_net_match:
        monthly_cumulative['net_sales'] = clean_yen(monthly_net_match.group(1))
        monthly_cumulative['net_items'] = int(monthly_net_match.group(2))
    if monthly_store_match:
        monthly_cumulative['store_sales'] = clean_yen(monthly_store_match.group(1))
    if monthly_total_match:
        monthly_cumulative['total_sales'] = clean_yen(monthly_total_match.group(1))
    result['monthly_cumulative'] = monthly_cumulative

    # ===== 業務・所感 =====
    # 本日の業務以降のテキスト
    task_match = re.search(r'本日の業務([\s\S]{0,200})', text)
    if task_match:
        task_text = task_match.group(1).strip()
        # 業務リスト以降の自由テキストを highlights に
        lines = task_text.split('\n')
        highlights_lines = []
        for line in lines:
            l = line.strip()
            if l and not l.startswith('・') and not l.startswith('□') and len(l) > 3:
                highlights_lines.append(l)
        if highlights_lines:
            result['highlights'] = ' '.join(highlights_lines[:3])

    return result

def split_into_reports(text):
    """チャットテキストを日報ブロックに分割"""
    reports = []

    # 日報の開始パターン: "X/XX(曜)" または "◯本日の出品" が含まれる行
    # LINE形式: "HH:MM 名前 テキスト" または "HH:MM 名前"の次行にテキスト
    lines = text.split('\n')

    current_block = []
    current_reporter = ''
    in_report = False

    for i, line in enumerate(lines):
        # 日付ヘッダー (2026.01.23 土曜日)
        if re.match(r'^\d{4}\.\d{2}\.\d{2}\s+[月火水木金土日]曜日', line.strip()):
            continue

        # 日報メッセージ: 日付パターンで始まる
        date_pattern = r'\d{1,2}/\d{1,2}[（(][月火水木金土日][）)]'

        # ブロックの終了条件: 新しい日報が始まったら保存
        if in_report and re.search(date_pattern, line) and (
            '◯本日の出品' in '\n'.join(lines[max(0,i):min(len(lines),i+5)]) or
            '本日の出品' in '\n'.join(lines[max(0,i):min(len(lines),i+3)])
        ):
            if current_block:
                reports.append('\n'.join(current_block))
            current_block = [line]
            continue

        if re.search(date_pattern, line):
            if ('本日の出品' in '\n'.join(lines[max(0,i):min(len(lines),i+10)]) or
                '◯本日の出品' in '\n'.join(lines[max(0,i):min(len(lines),i+10)])):
                if current_block:
                    reports.append('\n'.join(current_block))
                current_block = [line]
                in_report = True
                continue

        if in_report:
            current_block.append(line)

    if current_block:
        reports.append('\n'.join(current_block))

    return reports

def parse_all_reports(text, target_month=None, year='2026'):
    """全日報を解析してdictのリストを返す"""
    results = []

    # チャット全体を行で処理
    # LINEエクスポート形式に対応: "HH:MM 名前\n本文" または "HH:MM 名前 本文"
    # 日報らしきブロックを抽出

    # 日報の開始を日付パターンで検出
    # 例: "4/13(月)" や "3/31(火)"
    date_pattern = re.compile(r'(\d{1,2}/\d{1,2})[（(]([月火水木金土日])[）)]')

    # テキストを段落に分割
    paragraphs = re.split(r'\n(?=\d{1,2}/\d{1,2}[（(])', text)

    for para in paragraphs:
        # 日付パターンがあるか確認
        if not date_pattern.search(para):
            continue

        # 日報らしきセクションかチェック
        if '本日の出品' not in para and '月間累計' not in para:
            continue

        report = parse_report_block(para, year)
        if not report:
            continue

        if target_month and report.get('year_month') != target_month:
            continue

        results.append(report)

    return results

def update_sales_json(reports):
    """解析結果を sales.json に追記"""
    with open(DATA_FILE, 'r', encoding='utf-8') as f:
        data = json.load(f)

    added = 0
    updated = 0

    for report in reports:
        ym = report['year_month']
        day_key = report['day_key']

        # 月データの初期化
        if ym not in data['months']:
            data['months'][ym] = {
                'target': {'total_sales': 0, 'notes': ''},
                'days': {},
                'expenses': [],
                'summary': None
            }

        month = data['months'][ym]
        existing = month['days'].get(day_key)
        is_new = existing is None

        month['days'][day_key] = {
            'date': report['date'],
            'weekday': report['weekday'],
            'net_sales': report['net_sales'],
            'net_items': report['net_items'],
            'store_sales': report['store_sales'],
            'store_items': report['store_items'],
            'total_sales': report['total_sales'],
            'buy_count': report['buy_count'],
            'buy_items': report['buy_items'],
            'purchase_amount': report['purchase_amount'],
            'purchase_people': existing.get('purchase_people', 0) if existing else 0,
            'shiire_amount': existing.get('shiire_amount', 0) if existing else 0,
            'shiire_items': existing.get('shiire_items', 0) if existing else 0,
            'staff': report['staff'],
            'monthly_cumulative': report.get('monthly_cumulative', {}),
            'highlights': report.get('highlights', existing.get('highlights', '') if existing else ''),
            'raw_report': '',
        }

        if is_new:
            added += 1
        else:
            updated += 1

    # 月ごとに日付ソート
    for ym in data['months']:
        data['months'][ym]['days'] = dict(
            sorted(data['months'][ym]['days'].items(), key=lambda x: int(x[0]))
        )

    data['meta']['last_updated'] = datetime.now().strftime('%Y-%m-%d')

    with open(DATA_FILE, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

    return added, updated

def main():
    parser = argparse.ArgumentParser(description='スタクロ LINE チャットログ パーサー')
    parser.add_argument('file', nargs='?', help='チャットログファイル（省略時は stdin）')
    parser.add_argument('--month', help='対象月 (例: 2026-04)')
    parser.add_argument('--all', action='store_true', help='全月を処理')
    parser.add_argument('--year', default='2026', help='年 (デフォルト: 2026)')
    parser.add_argument('--dry-run', action='store_true', help='結果を表示するだけでJSONを更新しない')
    args = parser.parse_args()

    if args.file:
        with open(args.file, 'r', encoding='utf-8') as f:
            text = f.read()
    else:
        text = sys.stdin.read()

    target_month = args.month if not args.all else None
    reports = parse_all_reports(text, target_month, args.year)

    if not reports:
        print("日報が見つかりませんでした。")
        print("チャットログに '◯本日の出品' や '月間累計' が含まれているか確認してください。")
        sys.exit(1)

    print(f"\n検出した日報: {len(reports)} 件")
    for r in sorted(reports, key=lambda x: x['date']):
        print(f"  {r['date']}({r['weekday']}) ネット¥{r['net_sales']:,} 店頭¥{r['store_sales']:,} "
              f"買取{r['buy_count']}件 菊池{r['staff']['菊池']['listings']}点 島野{r['staff']['島野']['listings']}点")

    if args.dry_run:
        print("\n--dry-run: JSONは更新されませんでした")
        return

    added, updated = update_sales_json(reports)
    print(f"\n✅ 更新完了: {added} 件追加 / {updated} 件更新")
    print("次のステップ: python3 generate_dashboard.py でダッシュボードを更新")

if __name__ == '__main__':
    main()
