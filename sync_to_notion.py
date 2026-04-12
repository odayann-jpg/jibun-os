#!/usr/bin/env python3
"""自分OS → Notion 同期スクリプト
MDファイルの内容をNotionページに反映する

使い方:
    python3 sync_to_notion.py
"""

import os
import re
import requests
from pathlib import Path
from dotenv import load_dotenv

# .envからAPIキーを読み込む
load_dotenv(Path(__file__).parent / ".env")
NOTION_API_KEY = os.environ.get("NOTION_API_KEY")

BASE_DIR = Path(__file__).parent

# MDファイル → Notionページ ID のマッピング
PAGE_MAP = {
    "growth/skill-map.md":    "33ae06a12e5e80bf984cea4b7d7c52b1",
    "tasks/actions.md":       "33ae06a12e5e80589305e2ce295d0b0b",
    "growth/decision-log.md": "33ae06a12e5e80b78544f9bc5fcd77db",
}

HEADERS = {
    "Authorization": f"Bearer {NOTION_API_KEY}",
    "Content-Type": "application/json",
    "Notion-Version": "2022-06-28",
}


def rich_text(content):
    content = content[:2000]
    return [{"type": "text", "text": {"content": content}}]


def md_to_blocks(md_content):
    """MarkdownをNotionブロックのリストに変換"""
    blocks = []
    lines = md_content.split("\n")
    i = 0

    while i < len(lines):
        line = lines[i]

        # テーブルの検出
        if "|" in line and i + 1 < len(lines) and re.match(r"^\s*\|[-|\s:]+\|\s*$", lines[i + 1]):
            table_lines = []
            while i < len(lines) and "|" in lines[i]:
                table_lines.append(lines[i])
                i += 1

            # セパレーター行を除去
            table_rows = [
                row for row in table_lines
                if not re.match(r"^\s*\|[-|\s:]+\|\s*$", row)
            ]

            if table_rows:
                num_cols = max(len(row.split("|")) - 2 for row in table_rows)
                if num_cols < 1:
                    num_cols = 1

                table_block = {
                    "object": "block",
                    "type": "table",
                    "table": {
                        "table_width": num_cols,
                        "has_column_header": True,
                        "has_row_header": False,
                        "children": [],
                    },
                }

                for row in table_rows:
                    cells = [cell.strip() for cell in row.split("|")[1:-1]]
                    while len(cells) < num_cols:
                        cells.append("")
                    cells = cells[:num_cols]

                    table_block["table"]["children"].append({
                        "object": "block",
                        "type": "table_row",
                        "table_row": {
                            "cells": [
                                [{"type": "text", "text": {"content": cell[:2000]}}]
                                for cell in cells
                            ]
                        },
                    })

                blocks.append(table_block)
            continue

        # コードブロック
        if line.startswith("```"):
            code_lines = []
            i += 1
            while i < len(lines) and not lines[i].startswith("```"):
                code_lines.append(lines[i])
                i += 1
            code_content = "\n".join(code_lines)[:2000]
            blocks.append({
                "object": "block",
                "type": "code",
                "code": {
                    "rich_text": [{"type": "text", "text": {"content": code_content}}],
                    "language": "plain text",
                },
            })

        # 見出し
        elif line.startswith("# "):
            blocks.append({
                "object": "block",
                "type": "heading_1",
                "heading_1": {"rich_text": rich_text(line[2:])},
            })
        elif line.startswith("## "):
            blocks.append({
                "object": "block",
                "type": "heading_2",
                "heading_2": {"rich_text": rich_text(line[3:])},
            })
        elif line.startswith("### "):
            blocks.append({
                "object": "block",
                "type": "heading_3",
                "heading_3": {"rich_text": rich_text(line[4:])},
            })

        # 水平線
        elif line.strip() == "---":
            blocks.append({"object": "block", "type": "divider", "divider": {}})

        # 箇条書き
        elif line.startswith("- ") or line.startswith("* "):
            blocks.append({
                "object": "block",
                "type": "bulleted_list_item",
                "bulleted_list_item": {"rich_text": rich_text(line[2:])},
            })

        # 空行はスキップ
        elif line.strip() == "":
            pass

        # 通常の段落
        else:
            if line.strip():
                blocks.append({
                    "object": "block",
                    "type": "paragraph",
                    "paragraph": {"rich_text": rich_text(line)},
                })

        i += 1

    return blocks


def clear_page(page_id):
    """ページの既存ブロックを全削除"""
    url = f"https://api.notion.com/v1/blocks/{page_id}/children"
    response = requests.get(url, headers=HEADERS)
    response.raise_for_status()

    for block in response.json().get("results", []):
        requests.delete(
            f"https://api.notion.com/v1/blocks/{block['id']}",
            headers=HEADERS,
        )


def append_blocks(page_id, blocks):
    """ページにブロックを追加（100件ずつ）"""
    url = f"https://api.notion.com/v1/blocks/{page_id}/children"

    for i in range(0, len(blocks), 100):
        chunk = blocks[i : i + 100]
        response = requests.patch(url, headers=HEADERS, json={"children": chunk})
        if not response.ok:
            print(f"  エラー: {response.status_code}")
            print(f"  詳細: {response.text}")
            response.raise_for_status()


def sync_file(md_path, page_id):
    """MDファイルをNotionページに同期"""
    full_path = BASE_DIR / md_path
    if not full_path.exists():
        print(f"スキップ（ファイルなし）: {md_path}")
        return

    content = full_path.read_text(encoding="utf-8")
    blocks = md_to_blocks(content)

    print(f"同期中: {md_path} ({len(blocks)}ブロック)...")
    clear_page(page_id)
    append_blocks(page_id, blocks)
    print(f"  完了!")


def main():
    if not NOTION_API_KEY:
        print("エラー: NOTION_API_KEY が設定されていません (.envを確認してください)")
        return

    print("=== 自分OS → Notion 同期開始 ===\n")
    for md_path, page_id in PAGE_MAP.items():
        sync_file(md_path, page_id)

    print("\n=== 全ファイルの同期が完了しました ===")


if __name__ == "__main__":
    main()
