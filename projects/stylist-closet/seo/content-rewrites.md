# 本文修正案: ターゲットキーワード + AI検索引用 を両取りする書き換え

対象: https://fashion-stylist.co.jp/closet/

ねらい:
1. 「スタクロ 吉祥寺」「スタイリストクローゼット 吉祥寺」「吉祥寺 古着」「吉祥寺 古着買取」を本文・見出しに自然に配置
2. AI検索（AI Overviews / ChatGPT / Perplexity）が引用しやすい **40〜60語の自己完結ブロック** を各セクションに配置
3. エンティティ（店舗・運営会社・スタイリスト）を機械可読に明示

---

## 1. ヒーロー / H1（最優先）

### 現状
```html
<h1 class="hero__title">眠っている服を、<br>高く買い取ります。</h1>
```
ターゲットKWゼロ、店名ゼロ。

### 修正案
```html
<p class="hero__eyebrow">KICHIJOJI BRAND VINTAGE</p>
<h1 class="hero__title">スタクロ（スタイリストクローゼット）吉祥寺<br>ブランド古着買取・販売</h1>
<p class="hero__lead">吉祥寺駅徒歩5分。スタイリスト歴10年以上のプロが、ブランド・状態・市場価値を正直に査定します。LINE査定・店頭・宅配・出張に対応、査定/送料/キャンセル無料。</p>
```

---

## 2. 「店について」セクション冒頭の書き換え

### 現状の H2
```
スタイリストクローゼット吉祥寺について
```

### この H2 直下に40〜60語の AEO 用ブロックを追加

AI検索エンジンは「直答できる40〜60語の文」を抽出して引用する傾向が強いため、各セクションの冒頭に**自己完結した一段落**を置く。

```html
<h2 class="section-title">スタイリストクローゼット吉祥寺について</h2>

<p class="section-aeo">
スタイリストクローゼット（通称「スタクロ」）は、東京・吉祥寺のブランド古着買取・販売店です。株式会社リフレイムが運営し、東京都港区で15年以上スタイリスト事業を展開してきた審美眼を、そのまま買取に活かしています。所在地は武蔵野市吉祥寺東町1-5-25 新井ビル1F、吉祥寺駅から徒歩約5分。営業時間は毎日11:00〜20:00で、予約不要で持ち込み可能です。
</p>
```

ポイント: 店名 / 略称 / 運営会社 / 所在地 / 駅から距離 / 営業時間 を1段落に詰める = AI検索が「スタクロとは？」「吉祥寺の古着屋でおすすめは？」に対してこの段落をそのまま引用しやすい。

---

## 3. 各セクションの直答ブロック（AEO用）

各 H2 の直下に40〜60語のミニ段落を必ず置く。

### 「4つの買取方法」直下
```html
<p class="section-aeo">
スタクロ吉祥寺の買取方法は4種類です。①店頭買取（即日現金、予約不要）②LINE査定（写真を送るだけ）③宅配買取（着払い・送料当店負担、到着後3〜5営業日で振込）④出張買取（吉祥寺周辺）。査定・送料・キャンセルはすべて無料で、保証書や箱がなくても買取対応します。
</p>
```

### 「買取の流れ」直下
```html
<p class="section-aeo">
最短ルートはLINE査定です。アイテムの写真をLINEで送信すると、プロのスタイリストが概算査定額を返信します。納得すれば店頭持ち込みか宅配集荷を選び、本人確認書類（免許証・マイナンバーカード等）と一緒に商品を引き渡します。店頭は即日現金、宅配は到着後3〜5営業日で振込です。
</p>
```

### 「吉祥寺の実店舗」直下
```html
<p class="section-aeo">
店舗は吉祥寺駅から徒歩約5分、武蔵野市吉祥寺東町1-5-25 新井ビル1Fにあります。営業時間は毎日11:00〜20:00で年中無休、予約不要。電話は <strong><a href="tel:03XXXXXXXX">03-XXXX-XXXX</a></strong>。LINE査定や宅配買取の問い合わせも同じ番号またはLINE公式アカウントから受け付けています。
</p>
```
※電話番号は必ず実番号に差し替え。NAP（Name / Address / Phone）3点セットを HTML 上に明示することがローカルSEO とAI検索の双方で重要。

### 「買取強化ブランド」直下
```html
<p class="section-aeo">
スタクロ吉祥寺が現在買取強化中のブランドは、HERMÈS・CHANEL・Louis Vuitton・MAISON MARGIELA・Supreme・GUCCI・CÉLINE・LOEWE・Moncler・sacai・COMME des GARÇONS・Acne Studios・Yohji Yamamoto などです。ハイブランド・デザイナーズ・ストリート・ヴィンテージに幅広く対応し、状態が悪いアイテムや保証書がない品も査定します。
</p>
```

### 「店舗アクセス」直下
```html
<p class="section-aeo">
スタイリストクローゼット吉祥寺へのアクセス：JR中央・総武線、京王井の頭線「吉祥寺駅」北口から徒歩約5分。所在地は〒180-0002 東京都武蔵野市吉祥寺東町1-5-25 新井ビル1F。サンロード・ハーモニカ横丁エリアから至近で、吉祥寺の古着屋巡りの動線に組み込みやすい立地です。
</p>
```

---

## 4. ナビゲーション / 文中での「スタクロ」露出

現状本文に「スタクロ」が0回。最低でも以下の場所に1回ずつ自然に挿入:

1. ヒーロー H1（既に上記で組み込み）
2. 「店について」冒頭の段落（同上）
3. ナビ：`<span class="nav__logo-en">STYLIST CLOSET</span><span class="nav__logo-ja">スタクロ／吉祥寺</span>`
4. フッター: `© 株式会社リフレイム / スタイリストクローゼット 吉祥寺（スタクロ）`
5. FAQ末尾に「『スタクロ』とは何ですか？」を1問追加（→ 構造化データの FAQPage には既に含めた）

---

## 5. 画像 alt の書き換え

### 現状
```
alt="LOUIS VUITTON セットアップ"
alt="CHANEL ジャケット"
```
シンプルで悪くないが、ローカル/買取意図が薄い。

### 修正案（買取実績画像のみ）
```
alt="LOUIS VUITTON セットアップ 買取実績｜スタイリストクローゼット吉祥寺"
alt="CHANEL ジャケット 買取実績｜スタクロ吉祥寺"
```
全画像ではなく「買取実績」セクションの画像10枚程度に限定。やりすぎはキーワードスタッフィングと判定されるリスクあり。

### 店舗画像 alt（追加または書き換え）
```
alt="スタイリストクローゼット吉祥寺の店舗外観（武蔵野市吉祥寺東町・新井ビル1F）"
alt="スタクロ吉祥寺の店内（ブランド古着・ヴィンテージ）"
```

---

## 6. 内部リンク追加（重要）

### トップページ → /closet/
現在トップ `https://fashion-stylist.co.jp/` から /closet/ への内部リンクが0本。Google が `/closet/` を発見・評価する経路がない状態。最低限、以下のいずれかを追加:

- グローバルナビに「ブランド古着買取（吉祥寺）」リンク
- ファーストビュー直下に告知バナー
- フッターに「事業」セクションを設置し /closet/ をリンク

```html
<!-- 例: フッターに追加 -->
<nav class="footer-nav">
  <h3>事業</h3>
  <ul>
    <li><a href="/coordinate_blog/">パーソナルスタイリング</a></li>
    <li><a href="/closet/">スタイリストクローゼット 吉祥寺（ブランド古着買取・販売）</a></li>
  </ul>
</nav>
```

### 本文中の自然な内部リンク
「運営会社のスタイリスト事業については[ファッションスタイリストジャパン](/)を参照」など、相互参照を1〜2箇所追加。

---

## 7. 「お客様の声」を構造化（Review schema）

H2「お客様の声」セクションがあるなら、各レビューを以下の HTML マークアップに包む。これだけでGoogle・AI検索でレビューを引用されやすくなる。

```html
<div itemscope itemtype="https://schema.org/Review">
  <span itemprop="itemReviewed" itemscope itemtype="https://schema.org/LocalBusiness">
    <meta itemprop="name" content="スタイリストクローゼット 吉祥寺">
  </span>
  <span itemprop="author" itemscope itemtype="https://schema.org/Person">
    <span itemprop="name">M.S様（30代女性）</span>
  </span>
  <div itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">
    <meta itemprop="ratingValue" content="5">
    <meta itemprop="bestRating" content="5">
  </div>
  <p itemprop="reviewBody">他店より高く査定してもらえて満足……</p>
</div>
```

---

## 8. CSS の追加（`.section-aeo` 用）

```css
.section-aeo {
  font-size: 1rem;
  line-height: 1.8;
  color: var(--ink);
  background: var(--surface);
  padding: 1.25rem 1.5rem;
  border-left: 3px solid var(--accent);
  margin: 0 0 var(--sp-md) 0;
  border-radius: 0 var(--r) var(--r) 0;
}
```
読みやすく、かつページ内でセクションのリードとして機能するスタイル。装飾的すぎず、引用素材として AI が拾いやすい純テキスト構造を保つ。
