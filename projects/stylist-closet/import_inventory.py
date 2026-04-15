#!/usr/bin/env python3
"""
ベクタープレミアム 在庫CSV → inventory.json インポート

使い方:
  python3 import_inventory.py             # Downloadsの最新CSVを自動検出
  python3 import_inventory.py item_data.csv  # ファイル指定
"""

import csv, json, os, sys, glob
from datetime import datetime

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_FILE  = os.path.join(SCRIPT_DIR, 'data', 'inventory.json')

def find_latest_csv():
    """Downloads フォルダから最新の item_data*.csv を探す"""
    downloads = os.path.expanduser('~/Downloads')
    pattern   = os.path.join(downloads, 'item_data*.csv')
    files     = sorted(glob.glob(pattern), key=os.path.getmtime, reverse=True)
    return files[0] if files else None

def parse_csv(path):
    with open(path, encoding='shift-jis', errors='replace') as f:
        reader = csv.reader(f)
        headers = next(reader)
        rows = list(reader)

    def v(row, idx):
        return row[idx].strip("'") if idx < len(row) else ''

    def to_int(s):
        try: return int(s.replace(',', ''))
        except: return 0

    items = []
    for r in rows:
        price = to_int(v(r, 15))
        cost  = to_int(v(r, 16))
        items.append({
            'sku':   v(r, 8),
            'name':  v(r, 9),
            'brand': v(r, 70) or v(r, 21),
            'price': price,
            'cost':  cost,
        })
    return items

def build_inventory(items):
    total       = len(items)
    total_price = sum(i['price'] for i in items)
    total_cost  = sum(i['cost']  for i in items)

    def price_range(p):
        if p <= 5000:  return '〜5千'
        if p <= 10000: return '5千〜1万'
        if p <= 30000: return '1万〜3万'
        if p <= 50000: return '3万〜5万'
        return '5万〜'

    from collections import Counter
    dist = Counter(price_range(i['price']) for i in items)

    brand_totals = {}
    for i in items:
        b = i['brand']
        if not b: continue
        if b not in brand_totals:
            brand_totals[b] = {'count': 0, 'price': 0, 'cost': 0}
        brand_totals[b]['count'] += 1
        brand_totals[b]['price'] += i['price']
        brand_totals[b]['cost']  += i['cost']

    top_brands = sorted(brand_totals.items(), key=lambda x: -x[1]['price'])[:15]

    return {
        'meta': {
            'last_updated': datetime.now().strftime('%Y-%m-%d'),
            'source': 'vectorpremium'
        },
        'summary': {
            'total_items':  total,
            'total_price':  total_price,
            'total_cost':   total_cost,
            'gross_profit': total_price - total_cost,
            'avg_price':    round(total_price / total) if total else 0,
            'avg_cost':     round(total_cost  / total) if total else 0,
        },
        'price_distribution': {
            '〜5千':    dist.get('〜5千', 0),
            '5千〜1万': dist.get('5千〜1万', 0),
            '1万〜3万': dist.get('1万〜3万', 0),
            '3万〜5万': dist.get('3万〜5万', 0),
            '5万〜':    dist.get('5万〜', 0),
        },
        'top_brands': [
            {'name': b, 'count': d['count'], 'price': d['price'], 'cost': d['cost']}
            for b, d in top_brands
        ],
        'items': items
    }

def main():
    csv_path = sys.argv[1] if len(sys.argv) > 1 else None

    if not csv_path:
        csv_path = find_latest_csv()
        if csv_path:
            print(f"📂 自動検出: {os.path.basename(csv_path)}")
        else:
            print("❌ CSVが見つかりません。~/Downloads に item_data.csv を置いてください")
            sys.exit(1)

    if not os.path.exists(csv_path):
        print(f"❌ ファイルが見つかりません: {csv_path}")
        sys.exit(1)

    print(f"📥 インポート中: {csv_path}")
    items = parse_csv(csv_path)
    inv   = build_inventory(items)
    s     = inv['summary']

    with open(DATA_FILE, 'w', encoding='utf-8') as f:
        json.dump(inv, f, ensure_ascii=False, indent=2)

    low = len([i for i in items if i['price'] <= 5000])
    print(f"✅ inventory.json 更新完了")
    print(f"   在庫数:     {s['total_items']}件")
    print(f"   在庫金額:   ¥{s['total_price']:,}")
    print(f"   原価合計:   ¥{s['total_cost']:,}")
    print(f"   含み粗利:   ¥{s['gross_profit']:,}")
    print(f"   低単価(〜¥5千): {low}件 ({round(low/s['total_items']*100)}%)")

if __name__ == '__main__':
    main()
