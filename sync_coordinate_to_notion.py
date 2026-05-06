#!/usr/bin/env python3
"""Google Calendar → Notion タスク管理 DB 同期

コーディネート/同行を含む予定を Notion の「タスク管理」DB に追加する。
gcal_id プロパティで重複検知するので何度実行しても安全。

毎朝 8:01 に launchd（com.jibun_os.notion-coordinate-sync.plist）から起動される想定。

手動実行:
    python3 sync_coordinate_to_notion.py [--days 90] [--dry-run]
"""

import os
import sys
import time
import argparse
from pathlib import Path
from dotenv import load_dotenv
import requests

# 既存 sync_mms.py の関数を流用（Calendar 取得・期限抽出）
sys.path.insert(0, str(Path(__file__).parent))
from sync_mms import (
    _fetch_calendar_events,
    _get_calendar_service_and_id,
    _event_deadline_and_detail,
)

load_dotenv(Path(__file__).parent / ".env")
NOTION_API_KEY = os.environ.get("NOTION_API_KEY")
DATABASE_ID = "94129be9-d931-4b0f-8ce1-a7559f82840c"  # 自分OS / タスク管理

if not NOTION_API_KEY:
    print("エラー: NOTION_API_KEY が設定されていません (.env を確認してください)")
    sys.exit(1)

HEADERS = {
    "Authorization": f"Bearer {NOTION_API_KEY}",
    "Content-Type": "application/json",
    "Notion-Version": "2022-06-28",
}


def fetch_existing_gcal_ids():
    """Notion DB から既存タスクの gcal_id を全件取得"""
    gcal_ids = set()
    cursor = None
    url = f"https://api.notion.com/v1/databases/{DATABASE_ID}/query"
    while True:
        body = {"page_size": 100}
        if cursor:
            body["start_cursor"] = cursor
        r = requests.post(url, headers=HEADERS, json=body)
        if r.status_code != 200:
            print(f"エラー: Notion DB query 失敗 ({r.status_code}) {r.text[:300]}")
            sys.exit(1)
        data = r.json()
        for page in data.get("results", []):
            props = page.get("properties", {})
            gid_prop = props.get("gcal_id", {})
            rich = gid_prop.get("rich_text", [])
            if rich:
                gid = "".join(seg.get("plain_text", "") for seg in rich)
                if gid:
                    gcal_ids.add(gid)
        if not data.get("has_more"):
            break
        cursor = data.get("next_cursor")
    return gcal_ids


def is_target_event(summary):
    """コーディネート/同行を含むタイトル（リマインダー系は除外）"""
    if not summary:
        return False
    EXCLUDE = ("入力確認", "リマインダー", "確認のみ")
    if any(kw in summary for kw in EXCLUDE):
        return False
    return "コーディネート" in summary or "同行" in summary


def event_to_notion_page(event):
    """Calendar イベント → Notion page payload"""
    summary = (event.get("summary") or "").strip() or "(無題)"
    deadline, detail = _event_deadline_and_detail(event)
    gid = event.get("id", "")
    html_link = event.get("htmlLink", "")

    properties = {
        "タスク名": {"title": [{"text": {"content": summary[:200]}}]},
        "ステータス": {"select": {"name": "未着手"}},
        "領域": {"select": {"name": "西岡事業"}},
        "gcal_id": {"rich_text": [{"text": {"content": gid}}]},
    }
    if deadline:
        properties["期限"] = {"date": {"start": deadline}}

    body_lines = []
    if detail:
        body_lines.append(detail)
    body_lines.append(f"Google Calendar event ID: {gid}")
    if html_link:
        body_lines.append(f"イベント URL: {html_link}")
    body_text = "\n".join(body_lines)[:1900]

    return {
        "parent": {"database_id": DATABASE_ID},
        "properties": properties,
        "children": [
            {
                "object": "block",
                "type": "paragraph",
                "paragraph": {
                    "rich_text": [{"type": "text", "text": {"content": body_text}}]
                },
            }
        ],
    }


def create_notion_page(page):
    url = "https://api.notion.com/v1/pages"
    r = requests.post(url, headers=HEADERS, json=page)
    if r.status_code != 200:
        print(f"  作成失敗 ({r.status_code}): {r.text[:300]}")
        return False
    return True


def main():
    parser = argparse.ArgumentParser(prog="sync_coordinate_to_notion.py")
    parser.add_argument("--days", type=int, default=90, help="これから何日分を取り込むか（既定: 90）")
    parser.add_argument("--dry-run", action="store_true", help="Notion には書かず表示のみ")
    args = parser.parse_args()

    print("=== Google Calendar → Notion タスク管理 DB 同期 ===")

    service, cal_id = _get_calendar_service_and_id()
    items = _fetch_calendar_events(service, cal_id, args.days)
    targets = [ev for ev in items if is_target_event(ev.get("summary"))]
    n_coord = sum(1 for ev in targets if "コーディネート" in (ev.get("summary") or ""))
    n_dou = sum(1 for ev in targets if "同行" in (ev.get("summary") or ""))
    print(f"カレンダー: {len(items)} 件中、コーディネート: {n_coord} 件 / 同行: {n_dou} 件")

    print("Notion DB の既存 gcal_id を取得中...")
    existing = fetch_existing_gcal_ids()
    print(f"  既存登録: {len(existing)} 件")

    new_events = [ev for ev in targets if ev.get("id") and ev.get("id") not in existing]
    print(f"新規追加対象: {len(new_events)} 件")
    for ev in new_events:
        deadline, _ = _event_deadline_and_detail(ev)
        print(f"  - {deadline or '?'} | {(ev.get('summary') or '')[:60]}")

    if not new_events:
        print("追加なし。")
        return

    if args.dry_run:
        print("(--dry-run のため Notion には書きません)")
        return

    print("Notion に書き込み中...")
    success = 0
    failed = 0
    for ev in new_events:
        page = event_to_notion_page(ev)
        if create_notion_page(page):
            success += 1
        else:
            failed += 1
        time.sleep(0.3)  # Notion API rate limit 配慮（~3 req/sec）

    print(f"完了: {success} 件追加 / {failed} 件失敗")


if __name__ == "__main__":
    main()
