#!/usr/bin/env python3
"""Gemini Pro ライティングアシスタント

使い方:
    # 対話モード（何でも書ける）
    python3 gemini_writer.py

    # テーマを指定して生成
    python3 gemini_writer.py "紳士の身だしなみについて"

    # テンプレート指定
    python3 gemini_writer.py --type column "春のコーディネートの楽しみ方"
    python3 gemini_writer.py --type sns "新規会員募集"
    python3 gemini_writer.py --type mail "コーディネート日程確認"
    python3 gemini_writer.py --type blog "スタイリストの仕事とは"

    # ファイルに保存
    python3 gemini_writer.py --type column "テーマ" --save

    # カスタム指示を追加
    python3 gemini_writer.py "テーマ" --instruction "カジュアルな口調で"
"""

import json
import os
import sys
import argparse
from pathlib import Path
from datetime import datetime

try:
    import requests
except ImportError:
    print("エラー: requests が必要です → pip install requests")
    sys.exit(1)

BASE_DIR = Path(__file__).parent
ENV_FILE = BASE_DIR / ".env"
OUTPUT_DIR = BASE_DIR / ".company" / "secretary" / "notes"

GEMINI_MODEL = "gemini-2.5-pro"
GEMINI_URL = "https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent"

AVAILABLE_MODELS = {
    "pro": "gemini-2.5-pro",
    "flash": "gemini-2.5-flash",
    "nano-banana": "nano-banana-pro-preview",
}

TEMPLATES = {
    "column": {
        "name": "コラム",
        "system": (
            "あなたはジェントルマインドクラブのコラムライターです。\n"
            "紳士的で品のある文体で、読者に気づきを与えるコラムを書きます。\n"
            "800〜1200文字程度。段落分けを丁寧に。冒頭で興味を引き、最後にまとめを。"
        ),
    },
    "sns": {
        "name": "SNS投稿",
        "system": (
            "あなたはSNS投稿の専門家です。\n"
            "親しみやすく、共感を呼ぶ短い文章を書きます。\n"
            "絵文字は控えめに。ハッシュタグ候補も3〜5個提案。\n"
            "Instagram / X（Twitter）向けに最適化。200文字以内。"
        ),
    },
    "mail": {
        "name": "メール・案内文",
        "system": (
            "あなたはビジネスメールの専門家です。\n"
            "丁寧だが堅すぎない文体で、相手への敬意と温かみのある文章を書きます。\n"
            "件名案も含めてください。"
        ),
    },
    "blog": {
        "name": "ブログ記事",
        "system": (
            "あなたはスタイリスト事業のブログライターです。\n"
            "ファッション・コーディネート・身だしなみに関する記事を書きます。\n"
            "読みやすく、具体例を交えて。1500〜2500文字程度。SEOを意識した見出し構成。"
        ),
    },
    "free": {
        "name": "フリーライティング",
        "system": (
            "あなたは優秀なライターです。\n"
            "ユーザーの指示に合わせて最適な文章を書きます。\n"
            "文体・長さ・トーンは指示に従ってください。"
        ),
    },
}


def load_api_key():
    key = os.environ.get("GEMINI_API_KEY", "").strip()
    if key:
        return key
    if ENV_FILE.is_file():
        for line in ENV_FILE.read_text(encoding="utf-8").splitlines():
            if line.startswith("GEMINI_API_KEY="):
                return line.split("=", 1)[1].strip()
    print("エラー: GEMINI_API_KEY が見つかりません。.env に設定してください。")
    sys.exit(1)


def call_gemini(api_key, system_prompt, user_prompt, model=GEMINI_MODEL):
    url = GEMINI_URL.format(model=model)
    body = {
        "system_instruction": {"parts": [{"text": system_prompt}]},
        "contents": [{"role": "user", "parts": [{"text": user_prompt}]}],
        "generationConfig": {
            "temperature": 0.8,
            "topP": 0.95,
            "maxOutputTokens": 4096,
        },
    }
    resp = requests.post(
        url,
        params={"key": api_key},
        json=body,
        headers={"Content-Type": "application/json"},
        timeout=60,
    )
    if resp.status_code != 200:
        print(f"Gemini API エラー ({resp.status_code}):")
        try:
            err = resp.json()
            print(json.dumps(err, indent=2, ensure_ascii=False))
        except Exception:
            print(resp.text)
        sys.exit(1)

    data = resp.json()
    candidates = data.get("candidates", [])
    if not candidates:
        print("エラー: レスポンスが空です")
        sys.exit(1)
    parts = candidates[0].get("content", {}).get("parts", [])
    return "".join(p.get("text", "") for p in parts)


def interactive_mode(api_key):
    print("━" * 40)
    print("  Gemini Pro ライティングアシスタント")
    print("━" * 40)
    print()
    print("テンプレート:")
    for key, tmpl in TEMPLATES.items():
        print(f"  /{key:8s} → {tmpl['name']}")
    print()
    print("使い方: テーマや指示を入力。/column などでテンプレ切替。")
    print("終了: quit / exit / q")
    print()

    current_type = "free"
    system = TEMPLATES["free"]["system"]

    while True:
        try:
            user_input = input(f"[{TEMPLATES[current_type]['name']}] > ").strip()
        except (EOFError, KeyboardInterrupt):
            print("\n終了します。")
            break

        if not user_input:
            continue
        if user_input.lower() in ("quit", "exit", "q"):
            print("終了します。")
            break

        if user_input.startswith("/"):
            cmd = user_input[1:].split()[0]
            if cmd in TEMPLATES:
                current_type = cmd
                system = TEMPLATES[cmd]["system"]
                print(f"→ テンプレートを「{TEMPLATES[cmd]['name']}」に切り替えました\n")
                continue
            else:
                print(f"不明なテンプレート: {cmd}")
                continue

        print("\n生成中...\n")
        result = call_gemini(api_key, system, user_input)
        print("─" * 40)
        print(result)
        print("─" * 40)
        print()


def main():
    parser = argparse.ArgumentParser(
        description="Gemini Pro ライティングアシスタント"
    )
    parser.add_argument("theme", nargs="?", default="", help="テーマ・お題")
    parser.add_argument(
        "--type",
        "-t",
        choices=list(TEMPLATES.keys()),
        default="free",
        help="テンプレートの種類",
    )
    parser.add_argument("--instruction", "-i", default="", help="追加の指示")
    parser.add_argument("--save", "-s", action="store_true", help="結果をファイルに保存")
    parser.add_argument(
        "--model",
        "-m",
        default="pro",
        choices=list(AVAILABLE_MODELS.keys()),
        help="Geminiモデル: pro / flash / nano-banana (default: flash。pro/nano-bananaは課金設定が必要)",
    )
    args = parser.parse_args()

    api_key = load_api_key()

    if not args.theme:
        interactive_mode(api_key)
        return

    tmpl = TEMPLATES[args.type]
    system = tmpl["system"]
    if args.instruction:
        system += f"\n\n追加指示: {args.instruction}"

    prompt = f"テーマ: {args.theme}"

    print(f"テンプレート: {tmpl['name']}")
    print(f"テーマ: {args.theme}")
    if args.instruction:
        print(f"追加指示: {args.instruction}")
    print("\n生成中...\n")

    model = AVAILABLE_MODELS.get(args.model, GEMINI_MODEL)
    print(f"モデル: {model}")
    result = call_gemini(api_key, system, prompt, model=model)

    print("━" * 50)
    print(result)
    print("━" * 50)

    if args.save:
        OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
        today = datetime.now().strftime("%Y-%m-%d")
        filename = OUTPUT_DIR / f"{today}-writing.md"
        header = f"\n\n---\n\n## {tmpl['name']}: {args.theme}\n\n_生成: {datetime.now().strftime('%H:%M')}_\n\n"
        content = header + result + "\n"
        if filename.exists():
            with open(filename, "a", encoding="utf-8") as f:
                f.write(content)
        else:
            with open(filename, "w", encoding="utf-8") as f:
                f.write(f"# ライティング記録 {today}\n" + content)
        print(f"\n保存: {filename}")


if __name__ == "__main__":
    main()
