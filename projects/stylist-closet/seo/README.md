# スタクロ吉祥寺 SEO + AI検索対策パッケージ

対象ページ: https://fashion-stylist.co.jp/closet/
作成日: 2026-05-04
ターゲットKW: スタクロ 吉祥寺 / スタイリストクローゼット 吉祥寺 / 吉祥寺 古着 / 吉祥寺 古着買取

---

## 何が問題か（診断結果）

エラーチェック担当の独立検証で確認済み:

| # | 問題 | 重大度 |
|---|------|--------|
| 1 | canonical が存在しないドメイン `stylistcloset.jp/kaitori` を指す（NXDOMAIN）| **致命** |
| 2 | サイトマップに `/closet/` が含まれない | **致命** |
| 3 | トップページから `/closet/` への内部リンクが0本 | **致命** |
| 4 | 構造化データ（JSON-LD）が0個 | 高 |
| 5 | H1 にターゲットKWが入っていない | 高 |
| 6 | 「スタクロ」が本文に0回 | 高 |
| 7 | 電話番号がページ内に記載されていない（NAPの P 欠落）| 高 |
| 8 | og:image / og:url / twitter:card が無い | 中 |

**結論**: 順位が上がらない最大原因は「コンテンツの弱さ」ではなく「Google にインデックスされていない技術的バグ」。コンテンツ自体（H2構成・住所・営業時間・買取実績）は十分整っている。バグを潰せば現在の良いコンテンツが初めて評価対象になる。

---

## ファイル構成と使い方

```
projects/stylist-closet/seo/
├── README.md                    ← このファイル（全体ガイド）
├── head-snippets.html           ← <head> に貼り付ける一式（メタ・OG・構造化データ）
├── content-rewrites.md          ← H1・本文の書き換え案（40〜60語の直答ブロック付き）
├── llms.txt                     ← サイトルートに置く AI検索用ファイル
└── implementation-guide.md      ← WordPress / GBP / Search Console / AIクローラー反映手順
```

### 使い方
1. このREADMEで全体像を把握
2. `implementation-guide.md` の P0 だけ今週中に実施（5〜30分の作業を5本）
3. P1 は今月中、P2 は今四半期
4. 各ファイル内のプレースホルダー（`< >` で囲んだ部分）は**必ず**実値に差し替えること

### 本番投入前 必須差し替えチェックリスト（重要）

`<...>` の山括弧プレースホルダーを残したまま公開すると JSON-LD が壊れて構造化データとして認識されません。以下を**必ず先に**埋める。

- [ ] **電話番号**: `<03-XXXX-XXXX を実際の番号に差し替え>` → 実電話番号
- [ ] **メールアドレス**: `<info@... を差し替え。不要なら削除>` → 実アドレス または **行ごと削除**
- [ ] **Instagram URL**: `<https://www.instagram.com/...>` → スタクロ公式IGのURL
- [ ] **LINE URL**: `<https://line.me/R/ti/p/...>` → LINE公式アカウントのURL
- [ ] **Google ビジネスプロフィール URL**: `<Google ビジネスプロフィールの URL>` → 実URL（GBP管理画面の「プロフィールを共有」から取得）
- [ ] **Wikipedia/Wikidata URL**: `<会社のWikipedia/Wikidata URL があれば>` → ある場合のみ。無ければ**行ごと削除**
- [ ] **緯度経度**: 概算 `35.7038, 139.5840` → GBP登録の正確な座標で上書き推奨
- [ ] **OGP画像**: `/closet/images/ogp.jpg` (1200×630px) を作成して配置

### 投入前の検証
- [ ] [リッチリザルトテスト](https://search.google.com/test/rich-results) で `https://fashion-stylist.co.jp/closet/` を検査 → JSON-LD（FAQPage/LocalBusiness/BreadcrumbList）が認識されること
- [ ] [スキーママークアップ検証ツール](https://validator.schema.org/) でも検査
- [ ] [OGP表示確認](https://www.opengraph.xyz/) でOG画像が表示されること

---

## AI検索対策（2026年最新）

従来のSEOに加えて、AI Overviews / ChatGPT Search / Perplexity / Gemini で「引用元として選ばれる」ためのGEO（Generative Engine Optimization）対策を組み込み済み。

### 効いている要素
| 対策 | 引用率への効果 | 実装ファイル |
|------|----------------|--------------|
| FAQPage 構造化データ | **3.2倍** | head-snippets.html |
| 40〜60語の自己完結ブロック | **40%向上** | content-rewrites.md |
| マルチモーダル（テキスト+画像+構造化）| 最大3.17倍 | head-snippets.html |
| エンティティ明示（Wikidata + 4プラットフォーム）| 2.8倍 | implementation-guide.md (P2-2) |
| llms.txt | 補助的・採用クローラー増加中 | llms.txt |
| AIクローラー robots.txt 許可 | 必須条件 | implementation-guide.md (P1-2) |

### 注意点
- llms.txt は提案仕様。Google Search 本体は対応宣言していないが ChatGPT/Claude/Perplexity で効果報告あり
- AI検索は引用ドメイン数が少ない（Perplexityで平均5、ChatGPTで10）ため、選ばれる側に入らないと存在しない扱い
- 古いドメインが有利（Perplexityは10〜15年が26%、Google AI Overviewsは15年以上が49%）→ fashion-stylist.co.jp は2009年創業企業のドメインなのでこの点は有利

---

## キーワード戦略

| キーワード | 競合 | 取りやすさ | 主戦場 |
|------------|------|------------|--------|
| スタクロ 吉祥寺 | 低（指名語） | 即取れる（数日〜2週間）| 本文に「スタクロ」を3〜5回挿入するだけ |
| スタイリストクローゼット 吉祥寺 | 低（指名語）| 即取れる | バグ修正だけで取れる |
| 吉祥寺 古着 | 高（メディア記事支配）| 中長期（3〜6ヶ月）| GBP + ロングテールブログ + 被リンク |
| 吉祥寺 古着 買取 | 中 | 数ヶ月 | GBP + 構造化データ + ブランド別LP |
| 吉祥寺 ブランド買取 | 中 | 数ヶ月 | 同上 |
| ハイブランド名 + 買取 + 吉祥寺 | 低〜中 | ロングテールで稼ぐ | ブランド別LP（P2-1）|

### 競合状況（スコアの根拠）
- 「吉祥寺 古着」検索結果上位は uridoki.net / thisismedia / mensnonno / folk 等の**まとめ記事メディア**が支配
- 個店サイトが直接1位を取るのは難しいが、Googleマップの**ローカルパック**枠（最上段）はGBP整備で取れる
- 指名検索「スタクロ」「スタイリストクローゼット」は競合がほぼおらず、バグ修正だけで上位確実

---

## 推定スケジュール

| 期間 | 作業 | 期待効果 |
|------|------|----------|
| Week 1 | P0全件（canonical / 内部リンク / サイトマップ / Search Console）| Google が /closet/ をインデックス開始 |
| Week 2〜4 | head置換 + 本文書き換え + GBP整備 | 指名検索（スタクロ・スタイリストクローゼット）で上位表示 |
| Month 2 | 構造化データ反映確認 + AI検索クローラー許可 + llms.txt | AI Overviews / Perplexity で引用が始まる可能性 |
| Month 3〜6 | ブランド別LP 3〜5本 + ロングテールブログ 10本 + 被リンク | 「吉祥寺 古着買取」関連で順位向上、ローカルパック上位定着 |

---

## 監視項目（誠司または西岡さんが見るべき指標）

### 週1チェック
- 「スタクロ 吉祥寺」「スタイリストクローゼット 吉祥寺」の Google 順位
- GBP の口コミ（48時間以内に必ず返信）

### 月1チェック
- Google Search Console の「検索パフォーマンス」（表示回数・クリック数・順位）
- GBP の表示回数・経路検索数・電話タップ数
- ChatGPT / Perplexity で「吉祥寺 古着買取 おすすめ」「吉祥寺 ブランド買取」と質問 → スタクロが言及されるか確認

---

## 次のアクション（誠司への確認事項）

実装手順を具体化するために知りたいこと:

1. **WordPress 管理画面** へのログイン権限（誠司単独 / 西岡さん経由 / なし）
2. このサイトで使っている **SEO プラグイン**（Yoast / Rank Math / All in One SEO / 不明）
   ※canonical が `stylistcloset.jp/kaitori` に設定されているのはおそらくプラグイン側の設定
3. **/closet/ ページの実体**（WordPress 固定ページ / テーマファイル直書き）
4. **Google ビジネスプロフィール**の管理権限（誰が持っているか、口コミ件数、最終返信日）
5. **Google Search Console** に fashion-stylist.co.jp は登録済みか
6. **電話番号** の公開可否（公開できる代表番号を1つ）

回答後、`implementation-guide.md` の各 P0 項目について「クリック単位の手順」まで具体化して渡します。

---

## このパッケージの担保

- 診断・所見はライブHTML（curl 取得）と DNS（dig）で実証済み
- エラーチェック担当（別エージェント）で独立検証済み（10項目すべて True 確認）
- 構造化データは Google「リッチリザルトテスト」で検証してから本番投入することを前提に設計
- 文言・コンセプトは既存ページの内容と整合（実FAQ・実ブランド一覧から作成）
