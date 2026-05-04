# 実装ガイド: WordPress / GBP / Search Console / AI検索 への反映手順

対象: https://fashion-stylist.co.jp/closet/

優先度順に並んでいます。**P0 だけは今週中に必ず**動かしたい内容です。

---

## P0-1: canonical を直す（最重要、5分）

### 現状の致命バグ
```html
<link rel="canonical" href="https://stylistcloset.jp/kaitori" />
```
`stylistcloset.jp` は DNS 解決不能（NXDOMAIN）= 存在しないドメイン。Google にこのページがインデックスされていない最大の原因。

### 修正方法 A: SEO プラグイン（Yoast / Rank Math / All in One SEO）

1. WordPress 管理画面にログイン
2. 固定ページ → /closet/ ページを編集
3. ページ下部の SEO プラグイン設定欄で **Canonical URL** を探す
4. 値が `https://stylistcloset.jp/kaitori` になっているはずなので、これを `https://fashion-stylist.co.jp/closet/` に書き換え
5. 更新

### 修正方法 B: テーマファイル直書き

`/closet/` のテンプレートファイル（ページテンプレ or テーマの `page-closet.php` 等）の `<head>` 内で固定書きされている場合は、HTML を直接編集して `https://fashion-stylist.co.jp/closet/` に書き換え。

### 修正方法 C: 固定ページ本文に <head> ブロックを直書きしている場合

ページ HTML 直書き構造（現状の HTML を見る限り、これの可能性が最も高い。フォントや CSS 変数までページ内 `<style>` で定義されているため）：

1. WordPress 管理画面 → 固定ページ → /closet/ → 編集
2. ブロックエディタの「カスタム HTML」or「クラシック」モードで該当行を探す
3. `stylistcloset.jp/kaitori` の行を `https://fashion-stylist.co.jp/closet/` に置換
4. 更新

### 検証
```bash
curl -sL https://fashion-stylist.co.jp/closet/ | grep -i canonical
```
出力が `<link rel="canonical" href="https://fashion-stylist.co.jp/closet/" />` ならOK。

---

## P0-2: head セクションを丸ごと置換（30分）

`projects/stylist-closet/seo/head-snippets.html` の内容を、現状の `<head>` 内のメタ・OG・canonical 部分にそっくり置き換える。

### 差し替え前にやること（重要）
- プレースホルダー `<03-XXXX-XXXX を実際の番号に差し替え>` を実電話番号に
- `<https://www.instagram.com/...>` をスタクロ公式 IG に
- `<https://line.me/R/ti/p/...>` を LINE 公式アカウントに
- `<Google ビジネスプロフィールの URL>` を GBP プロフィール URL に
- `images/ogp.jpg` 用のOGP画像を作成して `/closet/images/ogp.jpg` に配置（1200×630px、店舗外観 or ロゴ + キャッチコピー）

### 検証
1. 変更を保存
2. https://search.google.com/test/rich-results に URL を入れて検査
3. JSON-LD が正しく認識されることを確認（FAQPage / LocalBusiness / BreadcrumbList が表示）
4. https://www.opengraph.xyz/ でOGP表示を確認

---

## P0-3: トップページから /closet/ への内部リンク追加（15分）

現状トップ `https://fashion-stylist.co.jp/` から `/closet/` への内部リンクは0本。Google にとって `/closet/` の存在が見えていない。

### 最低限の対応
WordPress 管理画面 → メニュー（外観 → メニュー）にリンク追加:
- ラベル: `ブランド古着買取（吉祥寺）`
- URL: `https://fashion-stylist.co.jp/closet/`

または、トップページ本文に1ブロック追加して /closet/ への動線を作る。

### 検証
```bash
curl -sL https://fashion-stylist.co.jp/ | grep -ic "/closet"
```
出力が 1 以上なら OK（現在は 0）。

---

## P0-4: サイトマップに /closet/ を追加（10分）

### Google Sitemap Generator プラグインの場合
1. WordPress 管理画面 → 設定 → XML-Sitemap
2. 「追加ページ」セクションで `https://fashion-stylist.co.jp/closet/` を手動追加
3. 優先度 0.9、更新頻度 monthly に設定
4. 「サイトマップを再構築」を実行

### または Yoast / Rank Math のサイトマップを使う場合
プラグインの管理画面で /closet/ を含めるオプションを有効化（デフォルトで含まれる場合が多い）。

### 検証
```bash
curl -sL https://fashion-stylist.co.jp/sitemap.xml | grep -i closet
```
ヒットすれば OK。

---

## P0-5: Google Search Console で URL 検査 → インデックス登録依頼（5分）

1. https://search.google.com/search-console/ にログイン
2. プロパティ `fashion-stylist.co.jp` を選択（未登録なら登録）
3. 上部の URL 検査バーに `https://fashion-stylist.co.jp/closet/` を入力
4. 結果が「URL は Google に登録されていません」と出るはず（canonical バグの影響）
5. 上記 P0-1〜P0-4 完了後に再度 URL 検査
6. 「インデックス登録をリクエスト」をクリック

通常、数日〜2週間でインデックス反映。

---

## P1-1: llms.txt をサイトルートに配置（10分）

1. `projects/stylist-closet/seo/llms.txt` のプレースホルダーを実値に差し替え
2. WordPress サーバーのドキュメントルートに `llms.txt` をアップロード（FTP / SFTP / WordPress プラグイン経由）
3. アクセス確認: https://fashion-stylist.co.jp/llms.txt が 200 で返ること

### 効果
ChatGPT / Claude / Perplexity がサイト概要を機械可読で取得しやすくなる。Google Search 本体は対応宣言していないが、AI検索クローラーでは効果報告あり。

---

## P1-2: AIクローラーの robots.txt 許可（5分）

WordPress サーバーの `/robots.txt` に以下を追加（現状は WordPress 標準のみ）:

```
# 既存の許可設定はそのまま残す

# AI検索クローラー明示許可
User-agent: GPTBot
Allow: /

User-agent: ChatGPT-User
Allow: /

User-agent: OAI-SearchBot
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: Perplexity-User
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: anthropic-ai
Allow: /

User-agent: Google-Extended
Allow: /

User-agent: Applebot-Extended
Allow: /

# サイトマップの位置を明示
Sitemap: https://fashion-stylist.co.jp/sitemap.xml
```

### 注意
robots.txt の Disallow を引き継ぐため、既存の WordPress 標準設定（wp-admin の Disallow）は削除しないこと。

---

## P1-3: Googleビジネスプロフィール（GBP）整備

「吉祥寺 古着」のような地域+カテゴリ検索ではローカルパック（マップ枠）が最上段。GBPの整備がオーガニック以上に効きます。

### チェックリスト
- [ ] ビジネス名: 「スタイリストクローゼット 吉祥寺」（略称を入れない、Googleガイドライン準拠）
- [ ] カテゴリ: メイン「古着屋」、サブ「中古品店」「リサイクルショップ」（Google公式カテゴリ一覧から選択。「買取専門店」は存在しないので注意）
- [ ] 住所: HTMLと完全一致（NAP一貫性）
- [ ] 電話: HTMLと完全一致
- [ ] ウェブサイト: `https://fashion-stylist.co.jp/closet/`（canonical 修正後）
- [ ] 営業時間: 11:00〜20:00 毎日
- [ ] 写真: 外観・内観・スタッフ・商品で30枚以上（少ないと評価低下）
- [ ] 投稿: 週1で「今週の入荷」「買取強化中ブランド」を投稿
- [ ] サービス: 店頭買取・LINE査定・宅配買取・出張買取 を個別登録
- [ ] 商品: 買取強化ブランド一覧を商品として登録
- [ ] Q&A: よくある質問を自分で投稿して自分で答える（最低5問）
- [ ] 口コミ返信: 全件、48時間以内に返信（2026年のローカルSEOで返信率がシグナル化）

### 口コミ返信のコツ
- 否定的な口コミも消さず、丁寧に返す（透明性が信頼につながる）
- ターゲットKW（吉祥寺・スタクロ・買取）を返信文に自然に含める
- 例: 「ご来店ありがとうございました。吉祥寺で古着買取をご検討中の方のお役に立てれば幸いです……」

---

## P1-4: 本文の書き換え（H1 / 各セクション直答ブロック）

`projects/stylist-closet/seo/content-rewrites.md` の手順に従って本文を書き換え。

固定ページ編集画面で1セクションずつ書き換え→保存→ライブ確認、の順で進める。

---

## P2-1: ブランド別 LP の作成（中長期）

`/closet/brand/hermes/`、`/closet/brand/louis-vuitton/` のように、買取強化ブランドごとに個別ページを作成。

### 各ページに含めるもの
- H1: 「エルメス（HERMÈS）買取｜吉祥寺のスタクロ」
- 直答ブロック（40〜60語）
- そのブランドの買取実績画像5〜10点（Product schema 付き）
- そのブランドの買取相場（FAQPage schema で書く）
- /closet/ への内部リンク

### 効果
「ハイブランド名 + 買取 + 吉祥寺」のロングテールKWを取りに行く。各ページから /closet/ へ内部リンクが集まり、/closet/ 本体の評価も上がる。

---

## P2-2: 第三者プラットフォーム（エンティティ強化）

AI検索の引用率は「Wikidata + 4つ以上の第三者プラットフォームで言及」で約2.8倍。

### 着手リスト
- [ ] Wikidata に「スタイリストクローゼット 吉祥寺」のエンティティを作成
- [ ] Google マップ クチコミに登場（GBP 経由）
- [ ] Tabelog や食べログのような業種別ディレクトリは古着屋向けには Pathee / 古着屋巡りマップガイド MEGURU が該当→掲載依頼
- [ ] 古着まとめメディア掲載: uridoki.net、thisismedia.media、folk-media.com にプレスリリース送付（「スタイリスト10年の目利き」は記事素材として強い）
- [ ] LinkedIn 公式ページ作成（AI検索引用率の高いプラットフォーム）
- [ ] Instagram の自己紹介に サイト URL とキーワード明示

---

## P2-3: ロングテールブログ（中長期、四半期で継続）

サイトには既に `/coordinate_blog/` カテゴリがあるので、その配下または `/closet/blog/` にブランド・地域・買取テーマのブログを継続投入。

### ネタ例（最初の10本）
1. 吉祥寺で CHANEL を高く売るコツ｜2026年最新相場
2. 吉祥寺の古着買取店比較｜ブランド古着ならスタクロ
3. ヴィンテージ MARGIELA の見分け方｜真贋判定のポイント
4. ハーモニカ横丁の古着屋巡り後に立ち寄る吉祥寺の買取専門店
5. 引っ越し前にやるべき古着の整理｜吉祥寺の宅配買取活用法
6. メンズのデザイナーズ古着 高価買取ブランド10選
7. レディース ハイブランドのバッグ買取 相場 2026
8. Supreme アーカイブの買取相場｜吉祥寺で売るならスタクロ
9. スタイリストが選ぶ「再販価値の高い古着」5つの条件
10. 古着買取の仕組み｜なぜスタイリストの査定は高いのか

各記事の冒頭に40〜60語の直答ブロックを必ず置く（AEO 対策）。

---

## チェックリスト全体（順序通りに）

### 今週（P0）
- [ ] canonical を `https://fashion-stylist.co.jp/closet/` に修正
- [ ] head セクションを head-snippets.html で置換
- [ ] トップページから /closet/ への内部リンク追加
- [ ] サイトマップに /closet/ を追加
- [ ] Search Console で URL 検査 → インデックス登録リクエスト
- [ ] OGP 画像 (1200x630) を作成して /closet/images/ogp.jpg に配置

### 今月（P1）
- [ ] llms.txt をルートに配置
- [ ] robots.txt に AIクローラー許可を追加
- [ ] GBP の整備（カテゴリ・写真30枚・サービス・商品・Q&A・口コミ返信）
- [ ] 本文の書き換え（H1 + 各セクション直答ブロック）
- [ ] 画像 alt の最適化
- [ ] 電話番号の表示（NAP揃え）
- [ ] お客様の声を Review schema 化

### 今四半期（P2）
- [ ] ブランド別 LP（少なくとも HERMÈS / Louis Vuitton / CHANEL の3つ）
- [ ] Wikidata エンティティ作成
- [ ] 古着まとめメディアへのプレス送付
- [ ] LinkedIn 公式ページ作成
- [ ] ロングテールブログ 10本

### 効果計測
- Google Search Console の「インデックス登録」「検索パフォーマンス」を月1回チェック
- 「スタクロ 吉祥寺」「スタイリストクローゼット 吉祥寺」の順位を週1回チェック
- GBP の表示回数・クリック数を月1回チェック
- AI検索（ChatGPT・Perplexity）で「吉祥寺 古着買取 おすすめ」と質問→ スタクロが言及されるか月1回確認
