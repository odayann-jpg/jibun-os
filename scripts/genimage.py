#!/usr/bin/env python3
"""
OpenAI画像生成スクリプト（gpt-image-1 / 将来 gpt-image-2 対応）

使い方:
    python3 scripts/genimage.py "プロンプト"
    python3 scripts/genimage.py "和風ペット撮影" --size 1024x1024 --quality medium
    python3 scripts/genimage.py "LPヒーロー画像" --size 1536x1024 --quality high --n 3
    python3 scripts/genimage.py "バナー" --out projects/stylist-photo/assets/images/

出力先: 未指定なら generated-images/YYYY-MM-DD/ に保存
"""

import argparse
import base64
import os
import re
import sys
from datetime import datetime
from pathlib import Path

try:
    from dotenv import load_dotenv
    from openai import OpenAI
except ImportError:
    print("エラー: 必要なライブラリが未インストール。以下を実行してください:")
    print("  python3 -m pip install openai python-dotenv")
    sys.exit(1)

MODEL = "gpt-image-1"  # gpt-image-2 API公開後はここを差し替え

VALID_SIZES = ["1024x1024", "1024x1536", "1536x1024", "auto"]
VALID_QUALITIES = ["low", "medium", "high", "auto"]


def slugify(text: str, max_len: int = 30) -> str:
    """日本語・記号を含むプロンプトをファイル名に使える形に変換"""
    text = re.sub(r"[\s/\\:*?\"<>|]+", "_", text)
    text = re.sub(r"_+", "_", text).strip("_")
    return text[:max_len] if text else "image"


def main():
    parser = argparse.ArgumentParser(description="OpenAI画像生成")
    parser.add_argument("prompt", help="生成したい画像の説明（日本語OK）")
    parser.add_argument("--size", default="1024x1024", choices=VALID_SIZES,
                        help="画像サイズ (default: 1024x1024)")
    parser.add_argument("--quality", default="medium", choices=VALID_QUALITIES,
                        help="品質 low/medium/high (default: medium)")
    parser.add_argument("--n", type=int, default=1, help="生成枚数 (default: 1)")
    parser.add_argument("--out", default=None, help="出力先フォルダ")
    parser.add_argument("--model", default=MODEL, help=f"使用モデル (default: {MODEL})")
    args = parser.parse_args()

    project_root = Path(__file__).resolve().parent.parent
    load_dotenv(project_root / ".env")

    api_key = os.getenv("OPENAI_API_KEY")
    if not api_key:
        print("エラー: OPENAI_API_KEY が .env にありません。")
        sys.exit(1)

    date_str = datetime.now().strftime("%Y-%m-%d")
    if args.out:
        out_dir = Path(args.out).resolve()
    else:
        out_dir = project_root / "generated-images" / date_str
    out_dir.mkdir(parents=True, exist_ok=True)

    client = OpenAI(api_key=api_key)

    print(f"生成中... モデル={args.model} サイズ={args.size} 品質={args.quality} 枚数={args.n}")
    print(f"プロンプト: {args.prompt}")

    try:
        result = client.images.generate(
            model=args.model,
            prompt=args.prompt,
            size=args.size,
            quality=args.quality,
            n=args.n,
        )
    except Exception as e:
        print(f"エラー: {e}")
        sys.exit(1)

    timestamp = datetime.now().strftime("%H%M%S")
    slug = slugify(args.prompt)
    saved_paths = []

    for i, img_data in enumerate(result.data, 1):
        suffix = f"_{i}" if args.n > 1 else ""
        filename = f"{timestamp}_{slug}{suffix}.png"
        filepath = out_dir / filename
        filepath.write_bytes(base64.b64decode(img_data.b64_json))
        saved_paths.append(filepath)
        print(f"  -> {filepath}")

    if hasattr(result, "usage") and result.usage:
        u = result.usage
        print(f"\nトークン使用量: 入力={getattr(u, 'input_tokens', '?')} "
              f"出力={getattr(u, 'output_tokens', '?')} "
              f"合計={getattr(u, 'total_tokens', '?')}")

    print(f"\n完了 ✓  保存先: {out_dir}")


if __name__ == "__main__":
    main()
