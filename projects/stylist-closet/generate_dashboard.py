#!/usr/bin/env python3
"""
スタクロ ダッシュボード生成スクリプト
sales.json を読み込んで dashboard.html を生成する

使い方: python3 generate_dashboard.py
"""

import json
import os
import calendar
from datetime import datetime, date

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_FILE = os.path.join(SCRIPT_DIR, 'data', 'sales.json')
OUTPUT_FILE = os.path.join(SCRIPT_DIR, 'dashboard.html')

WEEKDAY_JP = ['月', '火', '水', '木', '金', '土', '日']

def get_fixed_costs(fixed_costs_base, year_month):
    """月ごとの固定費を計算（5月からネット代追加）"""
    costs = dict(fixed_costs_base)
    year, month = year_month.split('-')
    if int(month) >= 5:
        costs['ネット回線'] = 10000
    return costs

def calc_month_summary(month_key, month_data, fixed_costs_base):
    """月次集計を計算"""
    days = month_data.get('days', {})
    expenses = month_data.get('expenses', [])
    fixed = get_fixed_costs(fixed_costs_base, month_key)

    total_net_sales = sum(d.get('net_sales', 0) for d in days.values())
    total_store_sales = sum(d.get('store_sales', 0) for d in days.values())
    total_sales = sum(d.get('total_sales', 0) for d in days.values())
    total_net_items = sum(d.get('net_items', 0) for d in days.values())
    total_store_items = sum(d.get('store_items', 0) for d in days.values())
    total_items = total_net_items + total_store_items

    # 月末サマリーのみの月（日次合計=0）は monthly_cumulative から補完
    last_day_key = max(days.keys(), key=lambda x: int(x)) if days else None
    if last_day_key:
        cum = days[last_day_key].get('monthly_cumulative', {})
        if cum.get('total_sales', 0) > total_sales:
            total_sales = cum['total_sales']
            total_net_sales = cum.get('net_sales', total_net_sales)
            total_store_sales = cum.get('store_sales', total_store_sales)
            total_net_items = cum.get('net_items', total_net_items)
            total_items = total_net_items + total_store_items
    total_buy_count = sum(d.get('buy_count', 0) for d in days.values())
    total_buy_items = sum(d.get('buy_items', 0) for d in days.values())
    total_buy_fail = sum(d.get('buy_fail_count', 0) for d in days.values())
    total_buy_visitors = sum(d.get('buy_visitors', 0) for d in days.values())
    total_purchase_amount = sum(d.get('purchase_amount', 0) for d in days.values())
    total_shiire_amount = sum(d.get('shiire_amount', 0) for d in days.values())
    total_shiire_items = sum(d.get('shiire_items', 0) for d in days.values())
    total_purchase_people = sum(d.get('purchase_people', 0) for d in days.values())

    active_days = len([d for d in days.values() if d.get('total_sales', 0) > 0 or d.get('net_sales', 0) > 0])

    # 月末予測（進行中の月のみ有効）
    year_m, month_m = month_key.split('-')
    days_in_month = calendar.monthrange(int(year_m), int(month_m))[1]
    today = date.today()
    is_current_month = (today.year == int(year_m) and today.month == int(month_m))
    if is_current_month and active_days > 0:
        elapsed = today.day
        daily_avg = total_sales / active_days
        remaining = days_in_month - elapsed
        forecast = round(total_sales + daily_avg * remaining)
    else:
        forecast = 0

    avg_item_price = total_sales / total_items if total_items > 0 else 0
    avg_net_price = total_net_sales / total_net_items if total_net_items > 0 else 0
    avg_daily_sales = total_sales / active_days if active_days > 0 else 0
    avg_purchase_per_person = total_purchase_amount / total_purchase_people if total_purchase_people > 0 else 0

    variable_expenses = sum(e.get('amount', 0) for e in expenses)
    fixed_total = sum(fixed.values())

    cogs = total_purchase_amount
    gross_profit = total_sales - cogs
    gross_profit_rate = gross_profit / total_sales * 100 if total_sales > 0 else 0
    # 仕入率 = 買取金額 ÷ 売上
    shiire_rate = total_purchase_amount / total_sales * 100 if total_sales > 0 else 0

    # 新規・リピート集計
    total_new_count = sum(d.get('buy_new_count', 0) for d in days.values())
    total_repeat_count = sum(d.get('buy_repeat_count', 0) for d in days.values())
    operating_expenses = variable_expenses + fixed_total
    operating_profit = gross_profit - operating_expenses
    operating_margin = operating_profit / total_sales * 100 if total_sales > 0 else 0

    target = month_data.get('target', {})
    target_sales = target.get('total_sales', 0)
    achievement_rate = total_sales / target_sales * 100 if target_sales > 0 else 0

    # スタッフ出品数（最終日のcumulativeから）
    staff_listings = {}
    for day_num in sorted(days.keys(), key=lambda x: int(x), reverse=True):
        cum = days[day_num].get('monthly_cumulative', {})
        if cum.get('staff_listings'):
            staff_listings = cum['staff_listings']
            break

    # 月間出品数合計（累計から取得、なければ日次合計）
    total_listings = sum(staff_listings.values()) if staff_listings else sum(
        (d.get('staff', {}).get('菊池', {}).get('listings', 0) or 0) +
        (d.get('staff', {}).get('島野', {}).get('listings', 0) or 0)
        for d in days.values()
    )

    # 出品数月末予測（進行中の月のみ）
    listings_forecast = 0
    listings_target = 600  # 月目標出品数
    if is_current_month and today.day > 0 and total_listings > 0:
        listing_daily_avg = total_listings / today.day
        listings_forecast = round(total_listings + listing_daily_avg * remaining)
    listings_rate = round(listings_forecast / listings_target * 100, 1) if listings_target > 0 and listings_forecast > 0 else 0

    # 週次集計
    year, month = month_key.split('-')
    weeks = {1: [], 2: [], 3: [], 4: [], 5: []}
    for day_num, day_data in sorted(days.items(), key=lambda x: int(x[0])):
        d = int(day_num)
        week_num = (d - 1) // 7 + 1
        weeks[week_num].append(day_data)

    weekly_totals = {}
    for wk, wk_days in weeks.items():
        if wk_days:
            weekly_totals[wk] = {
                'total_sales': sum(d.get('total_sales', 0) for d in wk_days),
                'buy_count': sum(d.get('buy_count', 0) for d in wk_days),
                'net_items': sum(d.get('net_items', 0) for d in wk_days),
                'days': len(wk_days)
            }

    return {
        'total_net_sales': total_net_sales,
        'total_store_sales': total_store_sales,
        'total_sales': total_sales,
        'total_net_items': total_net_items,
        'total_store_items': total_store_items,
        'total_items': total_items,
        'total_buy_count': total_buy_count,
        'total_buy_items': total_buy_items,
        'total_buy_fail': total_buy_fail,
        'total_buy_visitors': total_buy_visitors,
        'total_purchase_amount': total_purchase_amount,
        'total_shiire_amount': total_shiire_amount,
        'total_shiire_items': total_shiire_items,
        'total_purchase_people': total_purchase_people,
        'active_days': active_days,
        'forecast': forecast,
        'days_in_month': days_in_month,
        'avg_item_price': round(avg_item_price),
        'avg_net_price': round(avg_net_price),
        'avg_daily_sales': round(avg_daily_sales),
        'avg_purchase_per_person': round(avg_purchase_per_person),
        'cogs': cogs,
        'gross_profit': gross_profit,
        'gross_profit_rate': round(gross_profit_rate, 1),
        'shiire_rate': round(shiire_rate, 1),
        'total_new_count': total_new_count,
        'total_repeat_count': total_repeat_count,
        'fixed_total': fixed_total,
        'variable_expenses': variable_expenses,
        'operating_expenses': operating_expenses,
        'operating_profit': operating_profit,
        'operating_margin': round(operating_margin, 1),
        'achievement_rate': round(achievement_rate, 1),
        'target_sales': target_sales,
        'fixed_costs_detail': fixed,
        'variable_expenses_detail': expenses,
        'staff_listings': staff_listings,
        'total_listings': total_listings,
        'listings_forecast': listings_forecast,
        'listings_target': listings_target,
        'listings_rate': listings_rate,
        'weekly_totals': weekly_totals,
        'days': days
    }

def yen(n):
    """円表示"""
    if n is None:
        return '¥0'
    return f'¥{int(n):,}'

def pct(n):
    return f'{n:.1f}%'

def generate_html(data):
    """HTML を生成"""
    store_name = data['meta']['store_name']
    fixed_costs_base = data['fixed_costs']
    months_data = data['months']
    sorted_months = sorted(months_data.keys())

    # 全月サマリーを計算
    all_summaries = {}
    for mk in sorted_months:
        all_summaries[mk] = calc_month_summary(mk, months_data[mk], fixed_costs_base)

    # 月比較用データをJSON化
    months_json = []
    for mk in sorted_months:
        s = all_summaries[mk]
        year, month = mk.split('-')
        months_json.append({
            'key': mk,
            'label': f'{int(year)}年{int(month)}月',
            'total_sales': s['total_sales'],
            'net_sales': s['total_net_sales'],
            'store_sales': s['total_store_sales'],
            'total_items': s['total_items'],
            'buy_count': s['total_buy_count'],
            'buy_items': s['total_buy_items'],
            'buy_fail': s['total_buy_fail'],
            'buy_visitors': s['total_buy_visitors'],
            'purchase_amount': s['total_purchase_amount'],
            'shiire_amount': s['total_shiire_amount'],
            'forecast': s['forecast'],
            'days_in_month': s['days_in_month'],
            'gross_profit': s['gross_profit'],
            'gross_profit_rate': s['gross_profit_rate'],
            'shiire_rate': s['shiire_rate'],
            'new_count': s['total_new_count'],
            'repeat_count': s['total_repeat_count'],
            'operating_profit': s['operating_profit'],
            'operating_margin': s['operating_margin'],
            'avg_item_price': s['avg_item_price'],
            'avg_daily_sales': s['avg_daily_sales'],
            'target_sales': s['target_sales'],
            'achievement_rate': s['achievement_rate'],
            'staff_listings': s['staff_listings'],
            'total_listings': s['total_listings'],
            'listings_forecast': s['listings_forecast'],
            'listings_target': s['listings_target'],
            'listings_rate': s['listings_rate'],
            'weekly_totals': s['weekly_totals'],
            'days': {
                k: {
                    'net_sales': v.get('net_sales', 0),
                    'store_sales': v.get('store_sales', 0),
                    'total_sales': v.get('total_sales', 0),
                    'net_items': v.get('net_items', 0),
                    'buy_count': v.get('buy_count', 0),
                    'buy_items': v.get('buy_items', 0),
                    'buy_fail_count': v.get('buy_fail_count', 0),
                    'buy_visitors': v.get('buy_visitors', 0),
                    'buy_new_count': v.get('buy_new_count', 0),
                    'buy_repeat_count': v.get('buy_repeat_count', 0),
                    'purchase_amount': v.get('purchase_amount', 0),
                    'shiire_amount': v.get('shiire_amount', 0),
                    'shiire_items': v.get('shiire_items', 0),
                    'purchase_people': v.get('purchase_people', 0),
                    'weekday': v.get('weekday', ''),
                    'highlights': v.get('highlights', ''),
                    'staff': {
                        name: {
                            'listings': info.get('listings', 0),
                            'status': info.get('status', ''),
                            'cumulative': info.get('cumulative', 0),
                        }
                        for name, info in v.get('staff', {}).items()
                    },
                } for k, v in s['days'].items()
            },
            'pl': {
                'gross_profit': s['gross_profit'],
                'gross_profit_rate': s['gross_profit_rate'],
                'fixed_total': s['fixed_total'],
                'variable_expenses': s['variable_expenses'],
                'operating_profit': s['operating_profit'],
                'operating_margin': s['operating_margin'],
                'fixed_costs_detail': s['fixed_costs_detail'],
                'variable_expenses_detail': s['variable_expenses_detail'],
            }
        })

    months_js = json.dumps(months_json, ensure_ascii=False, indent=2)
    latest_month_key = sorted_months[-1] if sorted_months else ''
    year, month = latest_month_key.split('-') if latest_month_key else ('2026', '04')
    latest_label = f'{int(year)}年{int(month)}月'
    last_updated = data['meta']['last_updated']

    # 在庫データ読み込み
    inventory_file = os.path.join(SCRIPT_DIR, 'data', 'inventory.json')
    inventory_js = 'null'
    if os.path.exists(inventory_file):
        with open(inventory_file, 'r', encoding='utf-8') as f:
            inv = json.load(f)
        inventory_js = json.dumps(inv, ensure_ascii=False)

    # 収支計画データ読み込み
    pl_plan_file = os.path.join(SCRIPT_DIR, 'data', 'pl_plan.json')
    pl_plan_js = 'null'
    if os.path.exists(pl_plan_file):
        with open(pl_plan_file, 'r', encoding='utf-8') as f:
            pl_plan = json.load(f)
        pl_plan_js = json.dumps(pl_plan, ensure_ascii=False)

    html = f"""<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{store_name} 経営ダッシュボード</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<style>
:root {{
  --bg: #f4f6f9;
  --bg2: #ffffff;
  --bg3: #eef1f6;
  --border: #dde2eb;
  --text: #1a202c;
  --text2: #64748b;
  --accent: #1e3a5f;
  --accent2: #2c5282;
  --green: #1a6b45;
  --red: #c0392b;
  --blue: #2563a8;
  --purple: #5b4fcf;
}}
* {{ box-sizing: border-box; margin: 0; padding: 0; }}
body {{ background: var(--bg); color: var(--text); font-family: 'Hiragino Sans', 'Yu Gothic', sans-serif; font-size: 14px; }}
a {{ color: var(--accent); text-decoration: none; }}

/* Header */
.header {{ background: #1e3a5f; border-bottom: none; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 8px rgba(30,58,95,0.2); }}
.header h1 {{ font-size: 16px; font-weight: 700; letter-spacing: 0.05em; color: #ffffff; }}
.header .meta {{ font-size: 12px; color: rgba(255,255,255,0.65); }}

/* Month Nav */
.month-nav {{ background: var(--bg2); padding: 10px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; overflow-x: auto; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }}
.month-nav button {{ background: var(--bg3); border: 1px solid var(--border); color: var(--text2); padding: 6px 16px; border-radius: 20px; cursor: pointer; font-size: 13px; white-space: nowrap; transition: all 0.2s; }}
.month-nav button.active {{ background: #1e3a5f; color: #fff; border-color: #1e3a5f; font-weight: 600; }}
.month-nav button:hover:not(.active) {{ border-color: var(--accent); color: var(--accent); }}

/* Tab Nav */
.tab-nav {{ display: flex; gap: 0; border-bottom: 1px solid var(--border); background: var(--bg2); padding: 0 24px; }}
.tab-nav button {{ background: none; border: none; color: var(--text2); padding: 12px 18px; cursor: pointer; font-size: 13px; font-weight: 500; border-bottom: 2px solid transparent; transition: all 0.2s; }}
.tab-nav button.active {{ color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; }}
.tab-nav button:hover:not(.active) {{ color: var(--text); }}

/* Main */
.main {{ padding: 16px 20px; max-width: 1400px; margin: 0 auto; }}

/* Cards */
.cards {{ display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 24px; }}
.card {{ background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.05); }}
.card .label {{ font-size: 11px; color: var(--text2); margin-bottom: 6px; letter-spacing: 0.05em; font-weight: 500; }}
.card .value {{ font-size: 22px; font-weight: 700; color: var(--accent); }}
.card .sub {{ font-size: 11px; color: var(--text2); margin-top: 4px; }}
.card.green .value {{ color: var(--green); }}
.card.red .value {{ color: var(--red); }}
.card.blue .value {{ color: var(--blue); }}

/* Progress bar */
.progress-bar {{ background: var(--bg3); border-radius: 4px; height: 8px; margin-top: 8px; overflow: hidden; }}
.progress-bar .fill {{ height: 100%; background: var(--accent); border-radius: 4px; transition: width 0.5s; }}
.progress-bar .fill.over {{ background: var(--green); }}

/* Grid layouts */
.grid-2 {{ display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }}
.grid-3 {{ display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px; }}
@media (max-width: 900px) {{
  .grid-2, .grid-3 {{ grid-template-columns: 1fr; }}
  .cards {{ grid-template-columns: repeat(2, 1fr); }}
}}

/* Chart panels */
.panel {{ background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.05); }}
.panel h3 {{ font-size: 11px; font-weight: 600; color: var(--text2); margin-bottom: 10px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid var(--border); padding-bottom: 8px; }}
.chart-wrap {{ position: relative; max-height: 180px; }}

/* Table */
table {{ width: 100%; border-collapse: collapse; }}
thead th {{ background: #f8f8f8; color: var(--text2); font-size: 11px; font-weight: 600; padding: 10px 12px; text-align: right; letter-spacing: 0.04em; border-bottom: 2px solid var(--border); }}
thead th:first-child {{ text-align: left; }}
tbody td {{ padding: 10px 12px; text-align: right; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: var(--text); }}
tbody td:first-child {{ text-align: left; color: var(--text2); }}
tbody tr:hover {{ background: #fafafa; }}
.positive {{ color: var(--green); }}
.negative {{ color: var(--red); }}
.total-row td {{ font-weight: 700; color: var(--text); border-top: 2px solid var(--border); background: #fafafa; }}
.section-header td {{ background: #eef2f8; color: #1e3a5f; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }}
.indent {{ padding-left: 24px !important; color: var(--text) !important; }}

/* Daily table */
.daily-table {{ font-size: 12px; }}
.daily-table th, .daily-table td {{ padding: 7px 10px; }}
.highlights {{ font-size: 11px; color: var(--text2); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }}

/* Badge */
.badge {{ display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }}
.badge-sat {{ background: rgba(37,99,168,0.1); color: var(--blue); }}
.badge-sun {{ background: rgba(192,57,43,0.1); color: var(--red); }}

/* Tab content */
.tab-content {{ display: none; }}
.tab-content.active {{ display: block; }}

/* Diff column */
.diff-positive {{ color: var(--green); font-weight: 600; }}
.diff-negative {{ color: var(--red); font-weight: 600; }}

/* Week summary */
.week-grid {{ display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }}
.week-card {{ background: var(--bg3); border-radius: 6px; padding: 12px; text-align: center; }}
.week-card .wk-label {{ font-size: 11px; color: var(--text2); margin-bottom: 6px; }}
.week-card .wk-value {{ font-size: 16px; font-weight: 700; color: var(--accent); }}

/* Staff table */
.staff-bar {{ background: var(--bg3); border-radius: 3px; height: 12px; margin-top: 4px; overflow: hidden; display: flex; }}
.staff-bar .bar-fill {{ height: 100%; border-radius: 3px; transition: width 0.5s; }}

/* Category badges for expenses */
.cat-badge {{ display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; background: #f0f0f0; color: var(--text2); }}
</style>
</head>
<body>

<div class="header">
  <h1>📊 {store_name} 経営ダッシュボード</h1>
  <div class="meta">最終更新: {last_updated} | <span id="current-month-label">{latest_label}</span></div>
</div>

<div class="month-nav" id="month-nav"></div>

<div class="tab-nav">
  <button class="active" onclick="showTab('summary')">📊 今月サマリー</button>
  <button onclick="showTab('daily')">📅 日次データ</button>
  <button onclick="showTab('pl')">📊 収支計画</button>
  <button onclick="showTab('comparison')">📈 月比較</button>
  <button onclick="showTab('purchase')">🛍 買取分析</button>
  <button onclick="showTab('staff')">👥 スタッフ</button>
  <button onclick="showTab('inventory')">📦 在庫</button>
</div>

<div class="main">

<!-- ========== サマリー ========== -->
<div id="tab-summary" class="tab-content active">
  <div id="summary-cards" class="cards"></div>
  <div id="achievement-bar" style="margin-bottom:24px;display:none">
    <div class="panel">
      <h3>月次目標達成率</h3>
      <div id="achievement-content"></div>
    </div>
  </div>
  <div class="grid-2">
    <div class="panel">
      <h3>日次売上推移</h3>
      <div class="chart-wrap"><canvas id="chart-daily-sales" height="160"></canvas></div>
    </div>
    <div class="panel">
      <h3>週次サマリー</h3>
      <div id="weekly-summary"></div>
    </div>
  </div>
  <div class="grid-2">
    <div class="panel">
      <h3>売上チャネル内訳</h3>
      <div class="chart-wrap"><canvas id="chart-channel" height="150"></canvas></div>
    </div>
    <div class="panel">
      <h3>KPI サマリー</h3>
      <div id="kpi-table"></div>
    </div>
  </div>
</div>

<!-- ========== 日次データ ========== -->
<div id="tab-daily" class="tab-content">
  <div class="panel">
    <h3>日次データ一覧</h3>
    <div id="daily-table-wrap"></div>
  </div>
</div>

<!-- ========== 収支計画 ========== -->
<div id="tab-pl" class="tab-content">
  <div id="pl-plan-cards" class="cards" style="margin-bottom:24px"></div>
  <div class="panel" style="margin-bottom:16px">
    <h3>累計損益推移（計画 vs 実績）</h3>
    <div class="chart-wrap" style="max-height:200px"><canvas id="chart-cumulative" height="180"></canvas></div>
  </div>
  <div class="panel" style="margin-bottom:16px">
    <h3>月次 計画 vs 実績</h3>
    <div id="pl-comparison-table-wrap" style="overflow-x:auto"></div>
  </div>
  <div class="grid-2" style="margin-bottom:16px">
    <div class="panel">
      <h3>月次営業利益 計画 vs 実績</h3>
      <div class="chart-wrap"><canvas id="chart-op-profit" height="160"></canvas></div>
    </div>
    <div class="panel">
      <h3>月次 P/L 詳細（当月）</h3>
      <div id="pl-table-wrap"></div>
    </div>
  </div>
  <div class="panel">
    <h3>経費内訳</h3>
    <div id="expenses-table-wrap"></div>
  </div>
</div>

<!-- ========== 月比較 ========== -->
<div id="tab-comparison" class="tab-content">
  <div class="panel" style="margin-bottom:16px">
    <h3>月次売上推移</h3>
    <div class="chart-wrap"><canvas id="chart-monthly-sales" height="150"></canvas></div>
  </div>
  <div class="panel" style="margin-bottom:16px">
    <h3>月次比較テーブル</h3>
    <div id="comparison-table-wrap"></div>
  </div>
  <div class="grid-2">
    <div class="panel">
      <h3>買取比率推移</h3>
      <div class="chart-wrap"><canvas id="chart-margin" height="150"></canvas></div>
    </div>
    <div class="panel">
      <h3>1点単価・日販平均</h3>
      <div class="chart-wrap"><canvas id="chart-unit-price" height="150"></canvas></div>
    </div>
  </div>
</div>

<!-- ========== 買取分析 ========== -->
<div id="tab-purchase" class="tab-content">
  <div id="purchase-cards" class="cards" style="margin-bottom:24px"></div>
  <div class="panel" style="margin-bottom:16px">
    <h3>日次 買取件数 ＆ 買取金額</h3>
    <div class="chart-wrap"><canvas id="chart-buy-combined" height="160"></canvas></div>
  </div>
  <div class="panel">
    <h3>月次 買取推移</h3>
    <div class="chart-wrap"><canvas id="chart-monthly-purchase" height="150"></canvas></div>
  </div>
</div>

<!-- ========== スタッフ ========== -->
<div id="tab-staff" class="tab-content">
  <div id="staff-cards" class="cards" style="margin-bottom:24px"></div>
  <div class="grid-2">
    <div class="panel">
      <h3>スタッフ別 月間出品数累計</h3>
      <div class="chart-wrap"><canvas id="chart-staff-monthly" height="160"></canvas></div>
    </div>
    <div class="panel">
      <h3>日次 出品数</h3>
      <div class="chart-wrap"><canvas id="chart-staff-daily" height="160"></canvas></div>
    </div>
  </div>
</div>

<!-- ========== 在庫 ========== -->
<div id="tab-inventory" class="tab-content">
  <div id="inventory-updated" style="font-size:13px;color:var(--text2);margin-bottom:8px"></div>
  <div id="inventory-cards" class="cards" style="margin-bottom:24px"></div>
  <div class="grid-2">
    <div class="panel">
      <h3>価格帯別 在庫分布</h3>
      <div class="chart-wrap"><canvas id="chart-inv-price" height="160"></canvas></div>
    </div>
    <div class="panel">
      <h3>ブランド別 在庫金額 TOP10</h3>
      <div class="chart-wrap"><canvas id="chart-inv-brand" height="160"></canvas></div>
    </div>
  </div>
  <div class="panel" style="margin-top:16px">
    <h3>低単価在庫（¥2,000以下）— 業者まとめ売り候補</h3>
    <div id="low-price-note" style="color:var(--text2);font-size:13px;margin-bottom:12px"></div>
    <table class="data-table" id="low-price-table">
      <thead><tr><th>商品名</th><th>ブランド</th><th>販売価格</th><th>原価</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

</div><!-- /main -->

<script>
// ============ DATA ============
const SC_MONTHS = {months_js};
const SC_INVENTORY = {inventory_js};
const SC_PL_PLAN = {pl_plan_js};
const SC_STAFF_COLORS = ['#1e3a5f', '#2563a8', '#1a6b45', '#5b4fcf', '#c0392b'];

// ============ STATE ============
let currentMonthIdx = SC_MONTHS.length - 1;
let charts = {{}};
Chart.register(ChartDataLabels);
Chart.defaults.set('plugins.datalabels', {{ display: false }});

// ============ INIT ============
function init() {{
  buildMonthNav();
  renderMonth(currentMonthIdx);
}}

function buildMonthNav() {{
  const nav = document.getElementById('month-nav');
  SC_MONTHS.forEach((m, i) => {{
    const btn = document.createElement('button');
    btn.textContent = m.label;
    btn.onclick = () => renderMonth(i);
    btn.id = `month-btn-${{i}}`;
    nav.appendChild(btn);
  }});
}}

function renderMonth(idx) {{
  currentMonthIdx = idx;
  const m = SC_MONTHS[idx];
  document.getElementById('current-month-label').textContent = m.label;
  document.querySelectorAll('.month-nav button').forEach((b, i) => b.classList.toggle('active', i === idx));
  renderSummary(m, idx);
  renderDaily(m);
  renderPL(m);
  renderPurchase(m);
  renderStaff(m, idx);
  renderComparison();
}}

// ============ UTILS ============
function yen(n) {{ return '¥' + Math.round(n || 0).toLocaleString(); }}
function pct(n) {{ return (n || 0).toFixed(1) + '%'; }}
function diff(a, b) {{
  if (!b) return '';
  const d = ((a - b) / b * 100).toFixed(1);
  const cls = d >= 0 ? 'diff-positive' : 'diff-negative';
  const sign = d >= 0 ? '+' : '';
  return `<span class="${{cls}}">${{sign}}${{d}}%</span>`;
}}
function absChange(a, b) {{
  if (b == null) return '';
  const d = a - b;
  const cls = d >= 0 ? 'diff-positive' : 'diff-negative';
  const sign = d >= 0 ? '+' : '';
  return `<span class="${{cls}}">${{sign}}¥${{Math.abs(d).toLocaleString()}}</span>`;
}}
function destroyChart(id) {{
  if (charts[id]) {{ charts[id].destroy(); delete charts[id]; }}
}}
function getWeekdayClass(wd) {{
  if (wd === '土') return 'badge badge-sat';
  if (wd === '日') return 'badge badge-sun';
  return '';
}}

// ============ SUMMARY ============
function renderSummary(m, idx) {{
  const prev = idx > 0 ? SC_MONTHS[idx - 1] : null;

  // サマリーカード
  const totalSales = Object.values(m.days).reduce((s, d) => s + (d.total_sales || 0), 0);
  const netSales = Object.values(m.days).reduce((s, d) => s + (d.net_sales || 0), 0);
  const storeSales = Object.values(m.days).reduce((s, d) => s + (d.store_sales || 0), 0);
  const netItems = Object.values(m.days).reduce((s, d) => s + (d.net_items || 0), 0);
  const storeItems = Object.values(m.days).reduce((s, d) => s + (d.store_items || 0), 0);
  const totalItems = netItems + storeItems;
  const buyCount = Object.values(m.days).reduce((s, d) => s + (d.buy_count || 0), 0);
  const buyItems = Object.values(m.days).reduce((s, d) => s + (d.buy_items || 0), 0);
  const buyFail = Object.values(m.days).reduce((s, d) => s + (d.buy_fail_count || 0), 0);
  const buyVisitors = Object.values(m.days).reduce((s, d) => s + (d.buy_visitors || 0), 0);
  const buyRate = buyVisitors > 0 ? Math.round(buyCount / buyVisitors * 100) : 0;
  const purchaseAmt = Object.values(m.days).reduce((s, d) => s + (d.purchase_amount || 0), 0);
  const shiireAmt = Object.values(m.days).reduce((s, d) => s + (d.shiire_amount || 0), 0);
  const activeDays = Object.values(m.days).filter(d => d.total_sales > 0).length;
  const avgDaily = activeDays > 0 ? totalSales / activeDays : 0;
  const avgItem = totalItems > 0 ? totalSales / totalItems : 0;
  const cogs = purchaseAmt + shiireAmt;
  const gross = totalSales - cogs;
  const grossRate = totalSales > 0 ? gross / totalSales * 100 : 0;
  const shiireRate = totalSales > 0 ? purchaseAmt / totalSales * 100 : 0;
  const newCount = m.new_count || 0;
  const repeatCount = m.repeat_count || 0;

  const prevTotal = prev ? Object.values(prev.days).reduce((s, d) => s + (d.total_sales || 0), 0) : null;
  const forecast = m.forecast || 0;
  const daysInMonth = m.days_in_month || 30;

  const totalListings = m.total_listings || 0;
  const listingsForecast = m.listings_forecast || 0;
  const listingsTarget = m.listings_target || 600;
  const listingsRate = m.listings_rate || 0;
  const staffL = m.staff_listings || {{}};
  const kikuchiL = staffL['菊池'] || 0;
  const shimanoL = staffL['島野'] || 0;
  const staffSub = (kikuchiL > 0 || shimanoL > 0) ? `菊池${{kikuchiL}} / 島野${{shimanoL}}` : '—';

  const prevNetSales = prev ? Object.values(prev.days).reduce((s, d) => s + (d.net_sales || 0), 0) : null;
  const prevStoreSales = prev ? Object.values(prev.days).reduce((s, d) => s + (d.store_sales || 0), 0) : null;

  const cardsData = [
    {{ label: '月間売上', value: yen(totalSales), sub: prevTotal != null ? `前月比 ${{diff(totalSales, prevTotal).replace(/<[^>]+>/g, '')}}` : '—', cls: '' }},
    {{ label: 'EC売上', value: yen(netSales), sub: prevNetSales != null ? `前月比 ${{diff(netSales, prevNetSales).replace(/<[^>]+>/g, '')}} / ${{netItems}}点` : `${{netItems}}点`, cls: 'blue' }},
    {{ label: '店頭売上', value: yen(storeSales), sub: prevStoreSales != null ? `前月比 ${{diff(storeSales, prevStoreSales).replace(/<[^>]+>/g, '')}} / ${{storeItems}}点` : `${{storeItems}}点`, cls: '' }},
    forecast > 0 ? {{ label: '月末予測(売上)', value: yen(forecast), sub: `日販 ${{yen(Math.round(avgDaily))}}ペース`, cls: 'blue' }} : null,
    {{ label: '今月の出品数', value: totalListings + '点', sub: staffSub, cls: totalListings >= listingsTarget ? 'green' : '' }},
    listingsForecast > 0 ? {{ label: '月末出品予測', value: listingsForecast + '点', sub: `目標${{listingsTarget}}点 (${{listingsRate}}%)`, cls: listingsForecast >= listingsTarget ? 'green' : listingsForecast >= listingsTarget * 0.8 ? 'blue' : 'red' }} : null,
    {{ label: '仕入率', value: pct(shiireRate), sub: `買取 ${{yen(purchaseAmt)}}`, cls: shiireRate <= 30 ? 'green' : shiireRate <= 50 ? '' : 'red' }},
    (newCount + repeatCount) > 0 ? {{ label: '新規 / リピート', value: `${{newCount}} / ${{repeatCount}}`, sub: repeatCount > 0 ? `リピート率 ${{Math.round(repeatCount/(newCount+repeatCount)*100)}}%` : '—', cls: '' }} : null,
    {{ label: '日販平均', value: yen(avgDaily), sub: `稼働 ${{activeDays}}日`, cls: 'blue' }},
    {{ label: '1点単価', value: yen(avgItem), sub: `${{totalItems}}点販売`, cls: '' }},
    {{ label: '買取', value: buyCount + '成約 / ' + buyFail + '失注', sub: buyVisitors > 0 ? `来客${{buyVisitors}}組 成約率${{buyRate}}%` : yen(purchaseAmt), cls: '' }},
    buyVisitors > 0 ? {{ label: '買取金額', value: yen(purchaseAmt), sub: buyItems > 0 ? buyItems + '着' : '—', cls: '' }} : null,
    shiireAmt > 0 ? {{ label: '仕入れ', value: yen(shiireAmt), sub: '買取以外', cls: '' }} : null,
  ].filter(Boolean);

  const container = document.getElementById('summary-cards');
  container.innerHTML = cardsData.map(c => `
    <div class="card ${{c.cls}}">
      <div class="label">${{c.label}}</div>
      <div class="value">${{c.value}}</div>
      <div class="sub">${{c.sub}}</div>
    </div>
  `).join('');

  // 目標達成率
  if (m.target_sales > 0) {{
    const achRate = Math.min(totalSales / m.target_sales * 100, 150);
    document.getElementById('achievement-bar').style.display = 'block';
    document.getElementById('achievement-content').innerHTML = `
      <div style="display:flex;justify-content:space-between;margin-bottom:8px">
        <span>達成率: <strong style="color:var(--accent-2)">${{pct(totalSales / m.target_sales * 100)}}</strong></span>
        <span style="color:var(--text2)">${{yen(totalSales)}} / ${{yen(m.target_sales)}}</span>
      </div>
      <div class="progress-bar"><div class="fill ${{achRate >= 100 ? 'over' : ''}}" style="width:${{Math.min(achRate, 100)}}%"></div></div>
    `;
  }} else {{
    document.getElementById('achievement-bar').style.display = 'none';
  }}

  // 日次売上チャート
  destroyChart('daily-sales');
  const sortedDays = Object.keys(m.days).sort((a, b) => parseInt(a) - parseInt(b));
  const labels = sortedDays.map(d => m.days[d].weekday ? `${{d}}(${{m.days[d].weekday}})` : d);
  const netData = sortedDays.map(d => m.days[d].net_sales || 0);
  const storeData = sortedDays.map(d => m.days[d].store_sales || 0);
  const totalData = sortedDays.map((d, i) => (netData[i] || 0) + (storeData[i] || 0));
  charts['daily-sales'] = new Chart(document.getElementById('chart-daily-sales'), {{
    type: 'bar',
    data: {{
      labels,
      datasets: [
        {{ label: 'ネット', data: netData, backgroundColor: 'rgba(30,58,95,0.8)', stack: 'a',
           datalabels: {{ display: false }} }},
        {{ label: '店頭', data: storeData, backgroundColor: 'rgba(37,99,168,0.55)', stack: 'a',
           datalabels: {{
             display: ctx => totalData[ctx.dataIndex] > 0,
             anchor: 'end', align: 'end',
             formatter: (v, ctx) => totalData[ctx.dataIndex] > 0 ? '¥' + Math.round(totalData[ctx.dataIndex]/10000*10)/10 + '万' : '',
             color: '#444', font: {{ size: 10, weight: '600' }}
           }} }},
      ]
    }},
    options: {{
      responsive: true,
      plugins: {{
        legend: {{ labels: {{ color: '#999', font: {{ size: 11 }} }} }},
        datalabels: {{ display: false }}
      }},
      scales: {{
        x: {{ stacked: true, ticks: {{ color: '#888', font: {{ size: 10 }} }}, grid: {{ color: '#ebebeb' }} }},
        y: {{ stacked: true, ticks: {{ color: '#666', font: {{ size: 10 }}, callback: v => '¥' + (v/10000).toFixed(0) + '万' }}, grid: {{ color: '#ebebeb' }} }}
      }}
    }}
  }});

  // チャンネル円グラフ
  const netTotal = Object.values(m.days).reduce((s, d) => s + (d.net_sales || 0), 0);
  const storeTotal = Object.values(m.days).reduce((s, d) => s + (d.store_sales || 0), 0);
  destroyChart('channel');
  charts['channel'] = new Chart(document.getElementById('chart-channel'), {{
    type: 'doughnut',
    data: {{
      labels: ['ネット', '店頭'],
      datasets: [{{ data: [netTotal, storeTotal], backgroundColor: ['rgba(30,58,95,0.85)', 'rgba(37,99,168,0.65)'], borderWidth: 0 }}]
    }},
    options: {{
      responsive: true,
      plugins: {{
        legend: {{ position: 'bottom', labels: {{ color: '#999', font: {{ size: 11 }} }} }},
        tooltip: {{ callbacks: {{ label: ctx => `${{ctx.label}}: ¥${{ctx.raw.toLocaleString()}}` }} }}
      }}
    }}
  }});

  // 週次サマリー
  const weeklyEl = document.getElementById('weekly-summary');
  if (m.weekly_totals && Object.keys(m.weekly_totals).length > 0) {{
    const weeks = Object.entries(m.weekly_totals).filter(([, v]) => v.total_sales > 0 || v.days > 0);
    const prevWeekly = prev ? prev.weekly_totals : null;
    weeklyEl.innerHTML = `
      <table>
        <thead><tr><th>週</th><th>売上</th><th>買取</th><th>出品点</th>${{prevWeekly ? '<th>前月同週比</th>' : ''}}</tr></thead>
        <tbody>
          ${{weeks.map(([wk, v]) => {{
            const prevW = prevWeekly ? prevWeekly[wk] : null;
            return `<tr>
              <td>${{wk}}週</td>
              <td>${{yen(v.total_sales)}}</td>
              <td>${{v.buy_count}}件</td>
              <td>${{v.net_items}}点</td>
              ${{prevWeekly ? `<td>${{prevW ? diff(v.total_sales, prevW.total_sales) : '—'}}</td>` : ''}}
            </tr>`;
          }}).join('')}}
        </tbody>
      </table>`;
  }} else {{
    weeklyEl.innerHTML = '<p style="color:var(--text2);font-size:13px">データなし</p>';
  }}

  // KPIテーブル
  const kpiEl = document.getElementById('kpi-table');
  kpiEl.innerHTML = `
    <table>
      <tbody>
        <tr><td>月間売上合計</td><td>${{yen(totalSales)}}</td></tr>
        <tr><td>ネット売上</td><td>${{yen(netData.reduce((a, b) => a + b, 0))}}</td></tr>
        <tr><td>店頭売上</td><td>${{yen(storeData.reduce((a, b) => a + b, 0))}}</td></tr>
        <tr><td>総販売点数</td><td>${{totalItems}} 点</td></tr>
        <tr><td>1点単価（平均）</td><td>${{yen(avgItem)}}</td></tr>
        <tr><td>日販平均</td><td>${{yen(avgDaily)}}</td></tr>
        <tr><td>買取件数</td><td>${{buyCount}} 件</td></tr>
        <tr><td>買取金額合計</td><td>${{yen(purchaseAmt)}}</td></tr>
        <tr><td>売上−買取（差益）</td><td style="color:var(--green)">${{yen(gross)}}</td></tr>
        <tr><td>買取比率（仕入率）</td><td style="color:var(--green)">${{pct(grossRate)}}</td></tr>
      </tbody>
    </table>`;
}}

// ============ DAILY ============
function renderDaily(m) {{
  const days = Object.keys(m.days).sort((a, b) => parseInt(a) - parseInt(b));
  const el = document.getElementById('daily-table-wrap');
  if (!days.length) {{ el.innerHTML = '<p style="color:var(--text2)">データなし</p>'; return; }}
  // スタッフ名リスト（全日から収集）
  const staffNames = [...new Set(days.flatMap(d => Object.keys(m.days[d].staff || {{}})))].sort();

  el.innerHTML = `
    <div style="overflow-x:auto">
    <table class="daily-table">
      <thead>
        <tr>
          <th style="text-align:left">日</th>
          <th>ネット売上</th><th>点</th>
          <th>店頭売上</th><th>点</th>
          <th>合計</th>
          <th>買取(着)</th><th>成約</th><th>失注</th><th>来客</th><th>新規</th><th>リピート</th><th>買取金額</th>
          <th>仕入れ</th>
          ${{staffNames.map(n => `<th>${{n}}出品</th>`).join('')}}
          <th>特記</th>
        </tr>
      </thead>
      <tbody>
        ${{days.map(d => {{
          const v = m.days[d];
          const wdCls = getWeekdayClass(v.weekday);
          const staffCells = staffNames.map(n => {{
            const s = (v.staff || {{}})[n];
            if (!s) return '<td style="color:var(--text2)">—</td>';
            const st = s.status ? `<span style="font-size:10px;color:var(--text2)">${{s.status}}</span>` : '';
            return `<td>${{s.listings > 0 ? s.listings : ''}}${{st}}</td>`;
          }}).join('');
          return `<tr>
            <td>${{d}}${{wdCls ? `<span class="${{wdCls}}" style="margin-left:4px">${{v.weekday}}</span>` : `<span style="color:var(--text2);font-size:11px;margin-left:4px">${{v.weekday}}</span>`}}</td>
            <td>${{yen(v.net_sales)}}</td>
            <td>${{v.net_items || 0}}</td>
            <td>${{yen(v.store_sales)}}</td>
            <td>${{v.store_items || 0}}</td>
            <td style="font-weight:600">${{yen(v.total_sales)}}</td>
            <td>${{v.buy_items > 0 ? v.buy_items + '着' : ''}}</td>
            <td>${{v.buy_count > 0 ? `<span style="color:var(--green);font-weight:600">${{v.buy_count}}</span>` : ''}}</td>
            <td>${{v.buy_fail_count > 0 ? `<span style="color:var(--red)">${{v.buy_fail_count}}</span>` : ''}}</td>
            <td>${{v.buy_visitors > 0 ? v.buy_visitors : ''}}</td>
            <td>${{v.buy_new_count > 0 ? `<span style="color:var(--blue)">${{v.buy_new_count}}</span>` : ''}}</td>
            <td>${{v.buy_repeat_count > 0 ? `<span style="color:var(--purple)">${{v.buy_repeat_count}}</span>` : ''}}</td>
            <td>${{v.purchase_amount > 0 ? yen(v.purchase_amount) : ''}}</td>
            <td>${{v.shiire_amount > 0 ? yen(v.shiire_amount) : ''}}</td>
            ${{staffCells}}
            <td class="highlights" title="${{v.highlights || ''}}">${{v.highlights || ''}}</td>
          </tr>`;
        }}).join('')}}
        <tr class="total-row">
          <td>合計</td>
          <td>${{yen(days.reduce((s, d) => s + (m.days[d].net_sales || 0), 0))}}</td>
          <td>${{days.reduce((s, d) => s + (m.days[d].net_items || 0), 0)}}</td>
          <td>${{yen(days.reduce((s, d) => s + (m.days[d].store_sales || 0), 0))}}</td>
          <td>${{days.reduce((s, d) => s + (m.days[d].store_items || 0), 0)}}</td>
          <td>${{yen(days.reduce((s, d) => s + (m.days[d].total_sales || 0), 0))}}</td>
          <td>${{days.reduce((s, d) => s + (m.days[d].buy_items || 0), 0)}}着</td>
          <td>${{days.reduce((s, d) => s + (m.days[d].buy_count || 0), 0)}}成約</td>
          <td>${{days.reduce((s, d) => s + (m.days[d].buy_fail_count || 0), 0)}}失注</td>
          <td>${{days.reduce((s, d) => s + (m.days[d].buy_visitors || 0), 0)}}組</td>
          <td>${{days.reduce((s, d) => s + (m.days[d].buy_new_count || 0), 0)}}件</td>
          <td>${{days.reduce((s, d) => s + (m.days[d].buy_repeat_count || 0), 0)}}件</td>
          <td>${{yen(days.reduce((s, d) => s + (m.days[d].purchase_amount || 0), 0))}}</td>
          <td>${{yen(days.reduce((s, d) => s + (m.days[d].shiire_amount || 0), 0))}}</td>
          ${{staffNames.map(n => `<td>${{days.reduce((s, d) => s + ((m.days[d].staff || {{}})[n]?.listings || 0), 0)}}点</td>`).join('')}}
          <td></td>
        </tr>
      </tbody>
    </table>
    </div>`;
}}

// ============ PL ============
function renderPL(m) {{
  const plan = SC_PL_PLAN ? SC_PL_PLAN.months : {{}};

  // ── 実績計算 ──
  const days = Object.values(m.days);
  const actualSales    = days.reduce((s, d) => s + (d.total_sales || 0), 0);
  const actualNet      = days.reduce((s, d) => s + (d.net_sales || 0), 0);
  const actualStore    = days.reduce((s, d) => s + (d.store_sales || 0), 0);
  const actualCogs     = days.reduce((s, d) => s + (d.purchase_amount || 0), 0);
  const actualGross    = actualSales - actualCogs;
  const actualGrossRate = actualSales > 0 ? actualGross / actualSales * 100 : 0;
  const fixedCosts     = m.pl ? m.pl.fixed_costs_detail : {{}};
  const fixedTotal     = Object.values(fixedCosts).reduce((s, v) => s + v, 0);
  const varExpenses    = m.pl ? m.pl.variable_expenses : 0;
  const varDetail      = m.pl ? (m.pl.variable_expenses_detail || []) : [];
  const actualOpProfit = actualGross - fixedTotal - varExpenses;
  const actualOpMargin = actualSales > 0 ? actualOpProfit / actualSales * 100 : 0;

  // ── 計画値（当月） ──
  const mp = plan[m.key] || {{}};
  const planSales    = mp.total_sales || 0;
  const planGross    = mp.gross_profit || 0;
  const planOpProfit = mp.op_profit || 0;
  const planCumul    = mp.cumulative || 0;

  // ── 実績累計（月データから積算） ──
  let actualCumul = 0;
  for (const sm of SC_MONTHS) {{
    const smDays = Object.values(sm.days);
    const smSales = smDays.reduce((s, d) => s + (d.total_sales || 0), 0);
    const smCogs  = smDays.reduce((s, d) => s + (d.purchase_amount || 0), 0);
    const smGross = smSales - smCogs;
    const smFixed = sm.pl ? Object.values(sm.pl.fixed_costs_detail || {{}}).reduce((s,v)=>s+v,0) : 0;
    const smVar   = sm.pl ? (sm.pl.variable_expenses || 0) : 0;
    const smOp    = smGross - smFixed - smVar;
    if (smSales > 0) actualCumul += smOp;
    if (sm.key === m.key) break;
  }}

  // ── サマリーカード ──
  const salesDiff = actualSales - planSales;
  const opDiff    = actualOpProfit - planOpProfit;
  document.getElementById('pl-plan-cards').innerHTML = [
    {{ label: '計画売上',    value: yen(planSales),    sub: '月次目標' }},
    {{ label: '実績売上',    value: yen(actualSales),  sub: salesDiff >= 0 ? `計画比 +${{yen(salesDiff)}}` : `計画比 ${{yen(salesDiff)}}`, cls: salesDiff >= 0 ? 'green' : 'red' }},
    {{ label: '計画営業利益', value: yen(planOpProfit), sub: `計画買取比率 ${{pct(mp.gross_profit_rate || 70)}}`, cls: planOpProfit >= 0 ? '' : 'red' }},
    {{ label: '実績営業利益', value: yen(actualOpProfit), sub: opDiff >= 0 ? `計画比 +${{yen(opDiff)}}` : `計画比 ${{yen(opDiff)}}`, cls: actualOpProfit >= 0 ? 'green' : 'red' }},
    {{ label: '計画累計損益', value: yen(planCumul),   sub: '計画ベース', cls: planCumul >= 0 ? 'green' : 'red' }},
    {{ label: '実績累計損益', value: yen(actualCumul), sub: actualCumul >= 0 ? '✅ 黒字転換！' : `あと ${{yen(-actualCumul)}}`, cls: actualCumul >= 0 ? 'green' : 'red' }},
  ].map(c => `<div class="card ${{c.cls||''}}"><div class="label">${{c.label}}</div><div class="value">${{c.value}}</div><div class="sub">${{c.sub||''}}</div></div>`).join('');

  // ── 累計損益チャート（計画 vs 実績） ──
  destroyChart('cumulative');
  const allMonthKeys = SC_MONTHS.map(mm => mm.key).filter(k => plan[k]);
  const planKeys = Object.keys(plan).sort();
  const chartKeys = [...new Set([...planKeys, ...allMonthKeys])].sort();
  const planCumulData = chartKeys.map(k => plan[k] ? plan[k].cumulative : null);
  let runningActual = 0;
  const actualCumulData = chartKeys.map(k => {{
    const sm = SC_MONTHS.find(mm => mm.key === k);
    if (!sm) return null;
    const smDays = Object.values(sm.days);
    const smSales = smDays.reduce((s, d) => s + (d.total_sales || 0), 0);
    if (smSales === 0) return null;
    const smCogs  = smDays.reduce((s, d) => s + (d.purchase_amount || 0), 0);
    const smGross = smSales - smCogs;
    const smFixed = sm.pl ? Object.values(sm.pl.fixed_costs_detail || {{}}).reduce((s,v)=>s+v,0) : 0;
    const smVar   = sm.pl ? (sm.pl.variable_expenses || 0) : 0;
    runningActual += smGross - smFixed - smVar;
    return runningActual;
  }});
  const chartLabels = chartKeys.map(k => {{
    const [y, mo] = k.split('-');
    return `${{parseInt(y)%100}}/${{parseInt(mo)}}`;
  }});
  charts['cumulative'] = new Chart(document.getElementById('chart-cumulative'), {{
    type: 'line',
    data: {{
      labels: chartLabels,
      datasets: [
        {{ label: '計画累計',  data: planCumulData,   borderColor: '#2563a8', backgroundColor: 'rgba(37,99,168,0.08)', fill: true, tension: 0.3, borderDash: [5,3], pointRadius: 3, datalabels: {{display:false}} }},
        {{ label: '実績累計',  data: actualCumulData, borderColor: '#1a6b45', backgroundColor: 'rgba(26,107,69,0.1)',  fill: true, tension: 0.2, borderWidth: 2.5, pointRadius: 5, datalabels: {{display:false}} }},
      ]
    }},
    options: {{
      responsive: true,
      plugins: {{
        legend: {{ labels: {{ color: '#666', font: {{ size: 11 }} }} }},
        annotation: {{ annotations: {{ zeroLine: {{ type: 'line', yMin: 0, yMax: 0, borderColor: '#e74c3c', borderWidth: 1.5, borderDash: [4,4] }} }} }}
      }},
      scales: {{
        x: {{ ticks: {{ color: '#888', font: {{ size: 10 }} }}, grid: {{ color: '#ebebeb' }} }},
        y: {{ ticks: {{ color: '#666', font: {{ size: 10 }}, callback: v => (v>=0?'+':'')+Math.round(v/10000)+'万' }}, grid: {{ color: '#ebebeb' }},
              afterDataLimits: axis => {{ axis.min = Math.min(axis.min, -5000000); }} }}
      }}
    }}
  }});

  // ── 月次 計画vs実績 テーブル ──
  let runningOp = 0;
  const tableRows = chartKeys.map(k => {{
    const p = plan[k] || {{}};
    const sm = SC_MONTHS.find(mm => mm.key === k);
    const [y, mo] = k.split('-');
    const label = `${{parseInt(y)%100}}/${{parseInt(mo)}}`;
    let actSales = 0, actOp = null;
    if (sm) {{
      const smDays = Object.values(sm.days);
      actSales = smDays.reduce((s, d) => s + (d.total_sales || 0), 0);
      if (actSales > 0) {{
        const smCogs  = smDays.reduce((s, d) => s + (d.purchase_amount || 0), 0);
        const smGross = actSales - smCogs;
        const smFixed = sm.pl ? Object.values(sm.pl.fixed_costs_detail || {{}}).reduce((s,v)=>s+v,0) : 0;
        const smVar   = sm.pl ? (sm.pl.variable_expenses || 0) : 0;
        actOp = smGross - smFixed - smVar;
        runningOp += actOp;
      }}
    }}
    const sDiff = actSales > 0 ? actSales - (p.total_sales||0) : null;
    const oDiff = actOp !== null ? actOp - (p.op_profit||0) : null;
    const cumul = actOp !== null ? runningOp : null;
    const isCurrent = sm && sm.key === m.key;
    return `<tr style="${{isCurrent ? 'background:#eef2f8;font-weight:600' : ''}}">
      <td>${{label}}</td>
      <td>${{p.total_sales ? yen(p.total_sales) : '—'}}</td>
      <td>${{actSales > 0 ? yen(actSales) : '—'}}</td>
      <td style="color:${{sDiff === null ? '#999' : sDiff >= 0 ? 'var(--green)' : 'var(--red)'}}">${{sDiff === null ? '—' : (sDiff >= 0 ? '+' : '') + yen(sDiff)}}</td>
      <td style="color:${{(p.op_profit||0) >= 0 ? 'var(--green)' : 'var(--red)'}}">${{p.op_profit !== undefined ? yen(p.op_profit) : '—'}}</td>
      <td style="color:${{actOp === null ? '#999' : actOp >= 0 ? 'var(--green)' : 'var(--red)'}}">${{actOp !== null ? yen(actOp) : '—'}}</td>
      <td style="color:${{cumul === null ? '#999' : cumul >= 0 ? 'var(--green)' : 'var(--red)'}}">${{cumul !== null ? yen(cumul) : '—'}}</td>
    </tr>`;
  }}).join('');
  document.getElementById('pl-comparison-table-wrap').innerHTML = `
    <table style="font-size:12px">
      <thead><tr>
        <th style="text-align:left">月</th>
        <th>計画売上</th><th>実績売上</th><th>差額</th>
        <th>計画営業利益</th><th>実績営業利益</th><th>実績累計</th>
      </tr></thead>
      <tbody>${{tableRows}}</tbody>
    </table>`;

  // ── 月次営業利益グラフ ──
  destroyChart('op-profit');
  const opPlanData   = chartKeys.map(k => plan[k] ? plan[k].op_profit : null);
  let runActOp2 = 0;
  const opActualData = chartKeys.map(k => {{
    const sm = SC_MONTHS.find(mm => mm.key === k);
    if (!sm) return null;
    const smDays = Object.values(sm.days);
    const smSales = smDays.reduce((s, d) => s + (d.total_sales || 0), 0);
    if (smSales === 0) return null;
    const smCogs  = smDays.reduce((s, d) => s + (d.purchase_amount || 0), 0);
    const smFixed = sm.pl ? Object.values(sm.pl.fixed_costs_detail || {{}}).reduce((s,v)=>s+v,0) : 0;
    const smVar   = sm.pl ? (sm.pl.variable_expenses || 0) : 0;
    return smSales - smCogs - smFixed - smVar;
  }});
  charts['op-profit'] = new Chart(document.getElementById('chart-op-profit'), {{
    type: 'bar',
    data: {{
      labels: chartLabels,
      datasets: [
        {{ label: '計画', data: opPlanData, backgroundColor: 'rgba(37,99,168,0.45)', datalabels: {{display:false}} }},
        {{ label: '実績', data: opActualData, backgroundColor: opActualData.map(v => v === null ? 'transparent' : v >= 0 ? 'rgba(26,107,69,0.75)' : 'rgba(192,57,43,0.7)'), datalabels: {{display:false}} }},
      ]
    }},
    options: {{
      responsive: true,
      plugins: {{ legend: {{ labels: {{ color: '#666', font: {{ size: 11 }} }} }} }},
      scales: {{
        x: {{ ticks: {{ color: '#888', font: {{ size: 10 }} }} }},
        y: {{ ticks: {{ color: '#666', font: {{ size: 10 }}, callback: v => (v>=0?'+':'')+Math.round(v/10000)+'万' }}, grid: {{ color: '#ebebeb' }} }}
      }}
    }}
  }});

  // ── 月次 P/L 詳細（当月）A〜E構造 ──
  const el = document.getElementById('pl-table-wrap');
  const planOpex = mp.opex || {{}};
  const catLabels = {{
    'A_人件費': 'A. 人件費',
    'B_家賃・施設費': 'B. 家賃・施設費',
    'C_通信・システム費': 'C. 通信・システム費',
    'D_販売促進費': 'D. 販売促進費',
    'E_一般管理費': 'E. 一般管理費'
  }};
  const opexRows = Object.entries(catLabels).map(([key, label]) => {{
    const cat = planOpex[key] || {{}};
    const items = cat.items || {{}};
    const planCatTotal = cat.total || 0;
    // 実績: fixedCostsからカテゴリ内の各項目を合算
    const actCatTotal = Object.keys(items).reduce((s, k) => s + (fixedCosts[k] || 0), 0);
    const itemRows = Object.entries(items)
      .filter(([,v]) => v > 0)
      .map(([k,v]) => {{
        const actV = fixedCosts[k] || 0;
        return `<tr>
          <td class="indent" style="padding-left:32px;color:var(--text2)">${{k}}</td>
          <td>${{yen(v)}}</td>
          <td style="color:${{actV>0?'var(--text)':'var(--text2)'}}">${{actV>0?yen(actV):'—'}}</td>
          <td></td>
        </tr>`;
      }}).join('');
    return `
      <tr style="background:#f5f7fa"><td class="indent" style="font-weight:600;color:#1e3a5f">${{label}}</td><td>${{yen(planCatTotal)}}</td><td style="font-weight:600">${{actCatTotal>0?yen(actCatTotal):'—'}}</td><td style="color:var(--text2)">${{pct(actualSales>0?actCatTotal/actualSales*100:0)}}</td></tr>
      ${{itemRows}}`;
  }}).join('');
  const planOpexTotal = Object.values(planOpex).reduce((s,c)=>s+(c.total||0),0);
  el.innerHTML = `
    <table style="font-size:12px">
      <thead><tr><th style="text-align:left">項目</th><th>計画</th><th>実績</th><th>比率</th></tr></thead>
      <tbody>
        <tr class="section-header"><td colspan="4">I. 売上高</td></tr>
        <tr><td class="indent">ネット売上</td><td>${{yen(mp.net_sales||0)}}</td><td>${{yen(actualNet)}}</td><td>${{pct(actualSales>0?actualNet/actualSales*100:0)}}</td></tr>
        <tr><td class="indent">店頭売上</td><td>${{yen(mp.store_sales||0)}}</td><td>${{yen(actualStore)}}</td><td>${{pct(actualSales>0?actualStore/actualSales*100:0)}}</td></tr>
        <tr class="total-row"><td>純売上高</td><td>${{yen(planSales)}}</td><td>${{yen(actualSales)}}</td><td>100%</td></tr>
        <tr class="section-header"><td colspan="4">II. 売上原価</td></tr>
        <tr><td class="indent">当月買取仕入高</td><td>${{yen(mp.cogs||0)}}</td><td>${{yen(actualCogs)}}</td><td>${{pct(actualSales>0?actualCogs/actualSales*100:0)}}</td></tr>
        <tr class="total-row"><td>★ 売上総利益（粗利）</td><td style="color:var(--green)">${{yen(planGross)}}</td><td style="color:var(--green)">${{yen(actualGross)}}</td><td style="color:var(--green)">${{pct(actualGrossRate)}}</td></tr>
        <tr class="section-header"><td colspan="4">III. 販管費（A〜E）</td></tr>
        ${{opexRows}}
        ${{varExpenses>0?`<tr><td class="indent" style="font-weight:600">その他経費（実績）</td><td>—</td><td>${{yen(varExpenses)}}</td><td></td></tr>`:''}}
        <tr class="total-row"><td>★ 販管費合計</td><td>${{yen(planOpexTotal||mp.opex_total||0)}}</td><td>${{yen(fixedTotal+varExpenses)}}</td><td>${{pct(actualSales>0?(fixedTotal+varExpenses)/actualSales*100:0)}}</td></tr>
        <tr class="total-row" style="font-size:14px;border-top:3px solid var(--accent)">
          <td>★ 営業利益</td>
          <td style="color:${{planOpProfit>=0?'var(--green)':'var(--red)'}}">${{yen(planOpProfit)}}</td>
          <td style="color:${{actualOpProfit>=0?'var(--green)':'var(--red)'}}">${{yen(actualOpProfit)}}</td>
          <td style="color:${{actualOpProfit>=0?'var(--green)':'var(--red)'}}">${{pct(actualOpMargin)}}</td>
        </tr>
      </tbody>
    </table>`;

  // ── 経費内訳 ──
  const expEl = document.getElementById('expenses-table-wrap');
  if (varDetail.length) {{
    expEl.innerHTML = `
      <table>
        <thead><tr><th style="text-align:left">日付</th><th style="text-align:left">カテゴリ</th><th>金額</th><th style="text-align:left">メモ</th></tr></thead>
        <tbody>
          ${{varDetail.map(e=>`<tr><td>${{e.date||''}}</td><td>${{e.category||''}}</td><td>${{yen(e.amount)}}</td><td style="color:var(--text2)">${{e.memo||''}}</td></tr>`).join('')}}
        </tbody>
      </table>`;
  }} else {{
    expEl.innerHTML = '<p style="color:var(--text2);font-size:13px">経費の入力なし。Claudeに「経費を入力して」と話しかけてください。</p>';
  }}
}}

// ============ COMPARISON ============
function renderComparison() {{
  if (SC_MONTHS.length === 0) return;
  const labels = SC_MONTHS.map(m => m.label);
  const salesData = SC_MONTHS.map(m => Object.values(m.days).reduce((s, d) => s + (d.total_sales || 0), 0));
  const grossData = SC_MONTHS.map(m => {{
    const total = Object.values(m.days).reduce((s, d) => s + (d.total_sales || 0), 0);
    const cogs = Object.values(m.days).reduce((s, d) => s + (d.purchase_amount || 0), 0);
    return total - cogs;
  }});
  const grossRateData = SC_MONTHS.map((m, i) => salesData[i] > 0 ? (grossData[i] / salesData[i] * 100) : 0);
  const avgItemData = SC_MONTHS.map(m => {{
    const items = Object.values(m.days).reduce((s, d) => s + (d.net_items || 0) + (d.store_items || 0), 0);
    const total = Object.values(m.days).reduce((s, d) => s + (d.total_sales || 0), 0);
    return items > 0 ? Math.round(total / items) : 0;
  }});
  const avgDailyData = SC_MONTHS.map(m => {{
    const activeDays = Object.values(m.days).filter(d => d.total_sales > 0).length;
    const total = Object.values(m.days).reduce((s, d) => s + (d.total_sales || 0), 0);
    return activeDays > 0 ? Math.round(total / activeDays) : 0;
  }});

  // 月次売上チャート
  destroyChart('monthly-sales');
  charts['monthly-sales'] = new Chart(document.getElementById('chart-monthly-sales'), {{
    type: 'bar',
    data: {{
      labels,
      datasets: [
        {{ label: '売上', data: salesData, backgroundColor: 'rgba(30,58,95,0.8)', yAxisID: 'y',
           datalabels: {{ anchor: 'end', align: 'end', formatter: v => v > 0 ? '¥' + Math.round(v/10000*10)/10 + '万' : '', color: '#1e3a5f', font: {{ size: 11, weight: '700' }} }} }},
        {{ label: '粗利', data: grossData, backgroundColor: 'rgba(26,107,69,0.65)', yAxisID: 'y',
           datalabels: {{ display: false }} }},
      ]
    }},
    options: {{
      responsive: true,
      layout: {{ padding: {{ top: 24 }} }},
      plugins: {{
        legend: {{ labels: {{ color: '#555' }} }},
        datalabels: {{ display: true }}
      }},
      scales: {{
        x: {{ ticks: {{ color: '#888' }}, grid: {{ color: '#ebebeb' }} }},
        y: {{ ticks: {{ color: '#666', callback: v => '¥' + (v/10000).toFixed(0) + '万' }}, grid: {{ color: '#ebebeb' }} }}
      }}
    }}
  }});

  // 粗利率推移
  destroyChart('margin');
  charts['margin'] = new Chart(document.getElementById('chart-margin'), {{
    type: 'line',
    data: {{
      labels,
      datasets: [{{ label: '買取比率(%)', data: grossRateData, borderColor: '#1a6b45', backgroundColor: 'rgba(26,107,69,0.08)', tension: 0.3, fill: true }}]
    }},
    options: {{
      responsive: true,
      plugins: {{ legend: {{ labels: {{ color: '#555' }} }} }},
      scales: {{
        x: {{ ticks: {{ color: '#888' }}, grid: {{ color: '#ebebeb' }} }},
        y: {{ ticks: {{ color: '#666', callback: v => v + '%' }}, grid: {{ color: '#ebebeb' }}, min: 0, max: 100 }}
      }}
    }}
  }});

  // 1点単価・日販平均
  destroyChart('unit-price');
  charts['unit-price'] = new Chart(document.getElementById('chart-unit-price'), {{
    type: 'line',
    data: {{
      labels,
      datasets: [
        {{ label: '1点単価', data: avgItemData, borderColor: '#1e3a5f', backgroundColor: 'rgba(30,58,95,0.06)', tension: 0.3 }},
        {{ label: '日販平均', data: avgDailyData, borderColor: '#2563a8', backgroundColor: 'rgba(37,99,168,0.08)', tension: 0.3 }},
      ]
    }},
    options: {{
      responsive: true,
      plugins: {{ legend: {{ labels: {{ color: '#555' }} }} }},
      scales: {{
        x: {{ ticks: {{ color: '#888' }}, grid: {{ color: '#ebebeb' }} }},
        y: {{ ticks: {{ color: '#666', callback: v => '¥' + v.toLocaleString() }}, grid: {{ color: '#ebebeb' }} }}
      }}
    }}
  }});

  // 月比較テーブル
  const compEl = document.getElementById('comparison-table-wrap');
  compEl.innerHTML = `
    <table>
      <thead>
        <tr>
          <th style="text-align:left">指標</th>
          ${{SC_MONTHS.map(m => `<th>${{m.label}}</th>`).join('')}}
        </tr>
      </thead>
      <tbody>
        <tr><td>月間売上</td>${{SC_MONTHS.map((m, i) => `<td>${{yen(salesData[i])}}</td>`).join('')}}</tr>
        <tr><td>買取比率</td>${{SC_MONTHS.map((m, i) => `<td>${{pct(grossRateData[i])}}</td>`).join('')}}</tr>
        <tr><td>1点単価</td>${{SC_MONTHS.map((m, i) => `<td>${{yen(avgItemData[i])}}</td>`).join('')}}</tr>
        <tr><td>日販平均</td>${{SC_MONTHS.map((m, i) => `<td>${{yen(avgDailyData[i])}}</td>`).join('')}}</tr>
        <tr><td>前月比</td>${{SC_MONTHS.map((m, i) => `<td>${{i > 0 ? diff(salesData[i], salesData[i-1]) : '—'}}</td>`).join('')}}</tr>
      </tbody>
    </table>`;
}}

// ============ PURCHASE ============
function renderPurchase(m) {{
  const days = Object.keys(m.days).sort((a, b) => parseInt(a) - parseInt(b));
  const buyCountData = days.map(d => m.days[d].buy_count || 0);
  const buyAmtData = days.map(d => m.days[d].purchase_amount || 0);
  const labels = days.map(d => m.days[d].weekday ? `${{d}}(${{m.days[d].weekday}})` : d);

  const totalBuy = buyCountData.reduce((a, b) => a + b, 0);
  const totalAmt = buyAmtData.reduce((a, b) => a + b, 0);
  const totalPeople = Object.values(m.days).reduce((s, d) => s + (d.purchase_people || 0), 0);
  const avgPerPerson = totalPeople > 0 ? totalAmt / totalPeople : 0;
  const avgPerBuy = totalBuy > 0 ? totalAmt / totalBuy : 0;

  document.getElementById('purchase-cards').innerHTML = [
    {{ label: '買取件数', value: totalBuy + '件', sub: '今月合計' }},
    {{ label: '買取金額', value: yen(totalAmt), sub: '今月合計' }},
    {{ label: '1件平均', value: yen(avgPerBuy), sub: '買取単価' }},
    {{ label: '1人平均', value: yen(avgPerPerson), sub: totalPeople + '人' }},
  ].map(c => `<div class="card"><div class="label">${{c.label}}</div><div class="value">${{c.value}}</div><div class="sub">${{c.sub}}</div></div>`).join('');

  destroyChart('buy-combined');
  charts['buy-combined'] = new Chart(document.getElementById('chart-buy-combined'), {{
    type: 'bar',
    data: {{
      labels,
      datasets: [
        {{ label: '買取金額', data: buyAmtData, backgroundColor: 'rgba(201,169,110,0.8)', yAxisID: 'yAmt' }},
        {{ label: '買取件数', data: buyCountData, type: 'line', borderColor: '#5b4fcf', backgroundColor: 'rgba(91,79,207,0.15)', fill: true, tension: 0.3, yAxisID: 'yCnt', pointRadius: 4 }},
      ]
    }},
    options: {{
      responsive: true,
      interaction: {{ mode: 'index', intersect: false }},
      plugins: {{ legend: {{ labels: {{ color: '#555' }} }} }},
      scales: {{
        x: {{ ticks: {{ color: '#888', font: {{ size: 10 }} }}, grid: {{ color: '#ebebeb' }} }},
        yAmt: {{ position: 'left', ticks: {{ color: '#888', callback: v => '¥' + v.toLocaleString() }}, grid: {{ color: '#ebebeb' }} }},
        yCnt: {{ position: 'right', ticks: {{ color: '#5b4fcf', stepSize: 1 }}, grid: {{ drawOnChartArea: false }} }}
      }}
    }}
  }});

  // 月次買取推移
  const mLabels = SC_MONTHS.map(m => m.label);
  const mBuyCount = SC_MONTHS.map(m => Object.values(m.days).reduce((s, d) => s + (d.buy_count || 0), 0));
  const mBuyAmt = SC_MONTHS.map(m => Object.values(m.days).reduce((s, d) => s + (d.purchase_amount || 0), 0));
  destroyChart('monthly-purchase');
  charts['monthly-purchase'] = new Chart(document.getElementById('chart-monthly-purchase'), {{
    type: 'bar',
    data: {{
      labels: mLabels,
      datasets: [
        {{ label: '買取金額', data: mBuyAmt, backgroundColor: 'rgba(30,58,95,0.8)', yAxisID: 'y' }},
        {{ label: '買取件数', data: mBuyCount, type: 'line', borderColor: '#5b4fcf', backgroundColor: 'transparent', yAxisID: 'y2' }},
      ]
    }},
    options: {{
      responsive: true,
      plugins: {{ legend: {{ labels: {{ color: '#555' }} }} }},
      scales: {{
        x: {{ ticks: {{ color: '#888' }}, grid: {{ color: '#ebebeb' }} }},
        y: {{ ticks: {{ color: '#666', callback: v => '¥' + v.toLocaleString() }}, grid: {{ color: '#ebebeb' }}, position: 'left' }},
        y2: {{ ticks: {{ color: '#5b4fcf' }}, grid: {{ display: false }}, position: 'right' }}
      }}
    }}
  }});
}}

// ============ STAFF ============
function renderStaff(m, monthIdx) {{
  const staffNames = new Set();
  Object.values(m.days).forEach(d => Object.keys(d.staff || {{}}).forEach(n => staffNames.add(n)));
  const names = [...staffNames];
  const staffTotals = {{}};
  names.forEach(n => staffTotals[n] = 0);
  Object.values(m.days).forEach(d => {{
    Object.entries(d.staff || {{}}).forEach(([n, v]) => {{
      staffTotals[n] = (staffTotals[n] || 0) + (v.listings || 0);
    }});
  }});

  // カードに累計表示（最新日のcumulative）
  const cumulative = m.staff_listings || {{}};
  const totalListings = Object.values(cumulative).reduce((a, b) => a + b, 0) || Object.values(staffTotals).reduce((a, b) => a + b, 0);

  document.getElementById('staff-cards').innerHTML = [
    {{ label: '月間出品数合計', value: totalListings + '点', sub: '累計' }},
    ...names.map((n, i) => ({{ label: n, value: (cumulative[n] || staffTotals[n] || 0) + '点', sub: '今月累計' }}))
  ].map(c => `<div class="card"><div class="label">${{c.label}}</div><div class="value">${{c.value}}</div><div class="sub">${{c.sub}}</div></div>`).join('');

  // 月次スタッフ比較
  const mLabels = SC_MONTHS.map(m => m.label);
  const staffDatasets = names.map((name, ni) => {{
    const data = SC_MONTHS.map(month => {{
      const cum = month.staff_listings || {{}};
      if (cum[name] != null) return cum[name];
      return Object.values(month.days).reduce((s, d) => s + ((d.staff || {{}})[name]?.listings || 0), 0);
    }});
    return {{
      label: name,
      data,
      backgroundColor: `${{SC_STAFF_COLORS[ni % SC_STAFF_COLORS.length]}}bb`,
      borderColor: SC_STAFF_COLORS[ni % SC_STAFF_COLORS.length],
      borderWidth: 2
    }};
  }});

  destroyChart('staff-monthly');
  charts['staff-monthly'] = new Chart(document.getElementById('chart-staff-monthly'), {{
    type: 'bar',
    data: {{ labels: mLabels, datasets: staffDatasets }},
    options: {{
      responsive: true,
      plugins: {{ legend: {{ labels: {{ color: '#555' }} }} }},
      scales: {{
        x: {{ ticks: {{ color: '#888' }}, grid: {{ color: '#ebebeb' }} }},
        y: {{ ticks: {{ color: '#888' }}, grid: {{ color: '#ebebeb' }} }}
      }}
    }}
  }});

  // 日次スタッフ出品
  const days = Object.keys(m.days).sort((a, b) => parseInt(a) - parseInt(b));
  const labels = days.map(d => m.days[d].weekday ? `${{d}}(${{m.days[d].weekday}})` : d);
  const dailyDatasets = names.map((name, ni) => ({{
    label: name,
    data: days.map(d => (m.days[d].staff || {{}})[name]?.listings || 0),
    backgroundColor: `${{SC_STAFF_COLORS[ni % SC_STAFF_COLORS.length]}}bb`,
    stack: 'a'
  }}));

  destroyChart('staff-daily');
  charts['staff-daily'] = new Chart(document.getElementById('chart-staff-daily'), {{
    type: 'bar',
    data: {{ labels, datasets: dailyDatasets }},
    options: {{
      responsive: true,
      plugins: {{ legend: {{ labels: {{ color: '#555' }} }} }},
      scales: {{
        x: {{ stacked: true, ticks: {{ color: '#888', font: {{ size: 10 }} }}, grid: {{ color: '#ebebeb' }} }},
        y: {{ stacked: true, ticks: {{ color: '#888' }}, grid: {{ color: '#ebebeb' }} }}
      }}
    }}
  }});
}}

// ============ INVENTORY ============
function renderInventory() {{
  if (!SC_INVENTORY) {{
    document.getElementById('inventory-cards').innerHTML = '<p style="color:var(--text2)">在庫データなし。import_inventory.py を実行してください。</p>';
    return;
  }}
  const inv = SC_INVENTORY;
  const s = inv.summary;
  const lowItems = (inv.items || []).filter(i => i.price <= 2000);

  // 更新日表示
  const updatedEl = document.getElementById('inventory-updated');
  if (updatedEl && inv.meta && inv.meta.last_updated) {{
    updatedEl.textContent = `📅 在庫データ更新日: ${{inv.meta.last_updated}}（ベクタープレミアム CSV）`;
  }}

  // カード
  const cards = [
    {{ label: '在庫数', value: s.total_items + '件', sub: '出品中', cls: '' }},
    {{ label: '在庫金額', value: yen(s.total_price), sub: '販売価格ベース', cls: 'blue' }},
    {{ label: '原価合計', value: yen(s.total_cost), sub: `平均原価 ${{yen(s.avg_cost)}}`, cls: '' }},
    {{ label: '含み粗利', value: yen(s.gross_profit), sub: pct(s.total_price > 0 ? s.gross_profit/s.total_price*100 : 0), cls: 'green' }},
    {{ label: '平均販売価格', value: yen(s.avg_price), sub: '全在庫平均', cls: '' }},
    {{ label: '低単価在庫', value: lowItems.length + '件', sub: `全体の ${{Math.round(lowItems.length/s.total_items*100)}}%（¥2,000以下）`, cls: lowItems.length > 100 ? 'red' : '' }},
  ];
  document.getElementById('inventory-cards').innerHTML = cards.map(c => `
    <div class="card ${{c.cls}}">
      <div class="label">${{c.label}}</div>
      <div class="value">${{c.value}}</div>
      <div class="sub">${{c.sub}}</div>
    </div>`).join('');

  // 価格帯グラフ
  destroyChart('inv-price');
  const dist = inv.price_distribution;
  const distLabels = Object.keys(dist);
  const distData   = Object.values(dist);
  charts['inv-price'] = new Chart(document.getElementById('chart-inv-price'), {{
    type: 'bar',
    data: {{
      labels: distLabels,
      datasets: [{{ label: '件数', data: distData,
        backgroundColor: distData.map((_,i) => i===0 ? 'rgba(192,57,43,0.7)' : i===1 ? 'rgba(230,126,34,0.7)' : 'rgba(37,99,168,0.7)') }}]
    }},
    options: {{
      plugins: {{ legend: {{ display: false }}, datalabels: {{ display: true, anchor: 'end', align: 'top', formatter: v => v+'件', font: {{ size: 11 }} }} }},
      scales: {{ y: {{ beginAtZero: true }} }}
    }}
  }});

  // ブランドTOP10グラフ
  destroyChart('inv-brand');
  const brands = (inv.top_brands || []).slice(0,10);
  charts['inv-brand'] = new Chart(document.getElementById('chart-inv-brand'), {{
    type: 'bar',
    data: {{
      labels: brands.map(b => b.name.length > 12 ? b.name.substring(0,12)+'…' : b.name),
      datasets: [{{ label: '在庫金額', data: brands.map(b => b.price), backgroundColor: 'rgba(37,99,168,0.7)' }}]
    }},
    options: {{
      indexAxis: 'y',
      plugins: {{ legend: {{ display: false }}, datalabels: {{ display: false }} }},
      scales: {{ x: {{ ticks: {{ callback: v => '¥'+(v/10000).toFixed(0)+'万' }} }} }}
    }}
  }});

  // 低単価一覧
  document.getElementById('low-price-note').textContent =
    `¥2,000以下の在庫が ${{lowItems.length}}件（¥${{lowItems.reduce((s,i)=>s+i.price,0).toLocaleString()}}）あります。業者まとめ売りを検討してください。`;
  const tbody = document.querySelector('#low-price-table tbody');
  tbody.innerHTML = lowItems.slice(0,50).map(i => `
    <tr>
      <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${{i.name}}</td>
      <td>${{i.brand}}</td>
      <td>¥${{i.price.toLocaleString()}}</td>
      <td style="color:var(--text2)">¥${{i.cost.toLocaleString()}}</td>
    </tr>`).join('');
  if (lowItems.length > 50) {{
    tbody.innerHTML += `<tr><td colspan="4" style="text-align:center;color:var(--text2)">…他 ${{lowItems.length-50}}件</td></tr>`;
  }}
}}

// ============ TABS ============
function showTab(name) {{
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.tab-nav button').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  event.target.classList.add('active');
  if (name === 'inventory') renderInventory();
}}

// ============ START ============
init();
</script>
</body>
</html>"""
    return html

def main():
    if not os.path.exists(DATA_FILE):
        print(f"エラー: {DATA_FILE} が見つかりません")
        print("先に data/sales.json を作成してください")
        import sys
        sys.exit(1)

    with open(DATA_FILE, 'r', encoding='utf-8') as f:
        data = json.load(f)

    print(f"データ読み込み: {len(data['months'])} ヶ月分")

    html = generate_html(data)

    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        f.write(html)

    print(f"✅ ダッシュボード生成完了: {OUTPUT_FILE}")
    print(f"   ブラウザで開く: open {OUTPUT_FILE}")

if __name__ == '__main__':
    main()
