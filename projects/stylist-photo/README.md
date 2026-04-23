# スタイリストフォト（仮称）

スタクロ店舗内で展開予定のペット撮影スタジオのホームページ。
ファッションスタイリストがペット写真を撮影し、スタクロの古着レンタル・ワードローブ相談と連携するのが独自性。

## 閲覧方法

### 方法1: ブラウザで直接開く
```bash
open projects/stylist-photo/index.html
```

### 方法2: ローカルサーバーで起動（推奨）
```bash
cd projects/stylist-photo
python3 -m http.server 8000
# ブラウザで http://localhost:8000 を開く
```

## 現在のスコープ（フェーズA: デザインカンプ）

- `index.html`: トップページのLP
- `styles.css`: デザインシステム適用のCSS
- `assets/images/`: 画像配置用（現在はCDN画像を参照、差し替え予定）

**料金は空白**で表示しています（プラン設計継続検討のため）。
画像は本番撮影前のためフリー素材をプレースホルダーとして使用。

## ブランド方向性

- **ターゲット**: 30代以上、ペットに愛情を注げる層
- **トーン**: 優しい・ナチュラル・日常の延長線上にある特別
- **差別化**:
  - スタイリストが撮る（カメラマンではない）
  - 家の服でコーデ相談／スタクロ衣装レンタル
  - 全データお渡し
  - 1組限定・完全予約制
- **撮影体制**: スタクロ店舗内スタジオ基本 + 井の頭公園などロケオプション
- **営業時間**: 11:00〜20:00（最終受付 18:00）
- **最終公開先**: `fashion-stylist.co.jp/closet/photo/` 配下

## デザインシステム

| 項目 | 値 |
|---|---|
| ベース | `#FAF7F2`（オフホワイト） |
| メイン | `#A8B5A0`（セージグリーン） |
| サブ | `#C89B7B`（テラコッタ） |
| テキスト | `#3D3A36`（ダークブラウン） |
| 罫線 | `#E8E3DA` |
| 見出し | Noto Serif JP |
| 本文 | Noto Sans JP |
| 欧文 | Cormorant Garamond |

## 次段階の展望

- 予約フォーム（GMC赤坂の予約フォーム参考・LINE連携）
- 予約管理ダッシュボード（権限3段階：管理者・スタッフ・閲覧）
- LINE Messaging API 連携
- Google Calendar API 連携
- データベース・認証の本実装
- 下層ページ（プラン詳細・ギャラリー・FAQ）
- `fashion-stylist.co.jp/closet/photo/` への組み込み

## プランファイル

詳細は `/Users/hirotaseiji/.claude/plans/https-petstudio-sio-com-https-happy-phot-floofy-storm.md` を参照。
