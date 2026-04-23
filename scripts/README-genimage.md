# genimage.py — OpenAI画像生成スクリプト

ターミナルから呼び出していつでも画像生成ができるスクリプト。HP素材・スライド・資料など広く利用可能。

## 前提

- `.env` に `OPENAI_API_KEY=sk-proj-...` が設定済み（`自分OS/.env`）
- `pip install openai python-dotenv` 実行済み

## 基本の使い方

```bash
cd /Users/hirotaseiji/Desktop/自分OS

# シンプルに1枚生成（生成先: generated-images/YYYY-MM-DD/）
python3 scripts/genimage.py "和風のペット撮影スタジオ、自然光、優しい雰囲気"

# サイズ・品質を指定
python3 scripts/genimage.py "LPヒーロー画像、セージグリーン基調" --size 1536x1024 --quality high

# 3枚まとめて生成
python3 scripts/genimage.py "ロゴ候補、ミニマル" --n 3

# 出力先をプロジェクト配下に指定
python3 scripts/genimage.py "バナー画像" --out projects/stylist-photo/assets/images/
```

## オプション

| オプション | 値 | デフォルト | 説明 |
|---|---|---|---|
| `--size` | `1024x1024` / `1024x1536` / `1536x1024` / `auto` | `1024x1024` | 画像サイズ |
| `--quality` | `low` / `medium` / `high` / `auto` | `medium` | 品質（料金に影響） |
| `--n` | 数字 | `1` | 生成枚数 |
| `--out` | パス | `generated-images/YYYY-MM-DD/` | 出力先 |
| `--model` | モデル名 | `gpt-image-1` | 使用モデル |

## 料金目安（1枚あたり、$1=¥150換算）

| 解像度 | low | medium | high |
|---|---|---|---|
| 1024×1024 | $0.006 (≈¥1) | $0.053 (≈¥8) | $0.211 (≈¥32) |
| 1536×1024 / 1024×1536 | $0.005 | $0.041 | $0.165 (≈¥25) |

## gpt-image-2 への移行（5月以降）

OpenAI公式で `gpt-image-2` がAPI公開されたら、`scripts/genimage.py` の先頭の定数を変えるだけ：

```python
MODEL = "gpt-image-2"  # ここを変えるだけ
```

またはコマンドで毎回指定:
```bash
python3 scripts/genimage.py "プロンプト" --model gpt-image-2
```

## プロンプトのコツ

- **日本語OK**だが、英語のほうが安定しやすい
- スタイル指定を明確に: 「写真風」「イラスト風」「水彩」「ミニマル」など
- 色・雰囲気を指定: スタイリストフォト用なら `セージグリーン #A8B5A0、テラコッタ #C89B7B基調`
- 禁止事項は避ける: 著作権のあるキャラ・実在人物の写真は生成できない

## トラブル

- `エラー: OPENAI_API_KEY が .env にありません` → `.env` ファイルを確認
- `401 Unauthorized` → APIキーが間違っている or Revoke済み
- `insufficient_quota` → https://platform.openai.com/usage で残高チャージ
