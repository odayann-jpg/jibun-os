# Give & Take マネジメント LP — Design Spec

**Date:** 2026-04-29
**Owner:** 立花 由佳 (代表理事 / 一般社団法人 日本Give&Takeマネジメント協会)
**Driver:** 誠司 (LP制作担当 / Claude Code)
**Status:** Draft for review → implementation plan

---

## 1. Project context

### 1.1 The product
Give & Take マネジメントは、経営者男性向けのコーチング・コンサルティング。
配偶者・パートナーとの関係を「お互いが最良の状態を保ち、共通の未来を一緒に作る」という思想で扱う。「結婚前ギブアンドテイク」と同じ思想の延長線にあるが、本LPは独立した新しいブランド面として成立させる。

### 1.2 Audience
**経営者の男性**。事業は伸びている。結婚や未来の話は曖昧にしてきた。「今は仕事に集中したい」と言いながら、心のどこかで何かが引っかかっている。踏み切れない自分への戸惑いがある。

### 1.3 Use case (hybrid)
このLPは1つのページで2つの読まれ方を両立する：
- **既知の相手**：すでにやり取りがある相手に「これを見ておいてください」と送る → 読了後、次の打ち合わせに進む
- **新規の相手**：まだ知らない相手に届く → 読了後、「もっと話したい」と問い合わせ・打診に進む

### 1.4 Reading scene
夜の書斎やラウンジで、PC かスマホで開かれる。急かされず、最後まで読み進める想定。読了後の感情は「これはいいな」という静かな納得。売り込まれた感は出さない。

---

## 2. Design principles

### 2.1 The single most important principle
**装飾ではなく、構造とロジックで伝える。タイポグラフィとレイアウトの綺麗さで、上質さを出す。**

これがすべての判断の上位ルール。色・装飾・モーション・コピーで迷ったら、ここに戻る。

### 2.2 Tone
温かく親しみがある × 高級感。男女どちらにも寄らない。上質・洗練・整っている印象。

### 2.3 Anti-references (避けるもの)
- 派手なグラデーション
- 安っぽいスクールチラシのような雰囲気
- 昔の情報商材のような文字の羅列
- 強い言葉が並んでいる胡散臭いページ
- ベージュ背景

### 2.4 Voice
誠司・立花さんがすでに書き上げた本文（実コンテンツ）をそのまま使用する。LP制作側でコピーを書き換えない。レイアウトとタイポグラフィでコピーの呼吸を活かす。

---

## 3. Design system

### 3.1 Color palette

| Role | Token | Hex | Usage |
|---|---|---|---|
| Paper | `--paper` | `#ffffff` | Background |
| Ink | `--ink` | `#1a1a1a` | Headings (Hero, H2) |
| Body | `--body` | `#444444` | Body copy |
| Navy | `--navy` | `#1a3554` | Numerals, logo accent, dividers, key marks (✓, etc.) |
| Navy Soft | `--navy-soft` | `#4a5e80` | Italic eyebrows (EB Garamond italic) |
| Line | `--line` | `#d6dae3` | Section dividers, hairline rules |
| Line Subtle | `--line-subtle` | `#eeeeee` | Tertiary divisions |

すべての色は OKLCH に変換可能だが、初期実装では HEX で記述する。背景は **常に純白 #ffffff**（ベージュは禁止）。

### 3.2 Typography

| Role | Family | Size (desktop) | Line | Weight | Notes |
|---|---|---|---|---|---|
| Hero (H1) | Hiragino Mincho ProN | 30px | 1.7 | 400 | letter-spacing: 0.02em |
| Section H2 | Hiragino Mincho ProN | 24px | 1.65 | 400 | letter-spacing: 0.015em |
| Sub H3 | Hiragino Mincho ProN | 19px | 1.6 | 400 | |
| Pull quote | Hiragino Mincho ProN | 17px | 1.8 | 400 | left border: 2px solid Navy |
| Body | Hiragino Kaku Gothic ProN | 13.5px | 2.05 | 300 | color: var(--body) |
| Eyebrow italic | EB Garamond | 13px | — | italic | color: var(--navy-soft) |
| Profile name (英) | EB Garamond | 13px | — | italic | "Yuka Tachibana" などの英字併記 — color: var(--navy-soft) |
| Numeral (I., II.) | EB Garamond | 32px | 1 | 400 | color: var(--navy) |
| Caption / Label | Hiragino Kaku Gothic ProN | 10–11px | — | 400 | letter-spacing: 0.22–0.32em / UPPERCASE |
| Logo (G&T) | EB Garamond | 22px | 1 | 400 | letter-spacing: 0.04em / color: var(--navy) |

**Font stack fallbacks:**
- 明朝: `"Hiragino Mincho ProN", "Yu Mincho", "YuMincho", serif`
- ゴシック: `"Hiragino Kaku Gothic ProN", "Yu Gothic", "YuGothic", system-ui, sans-serif`
- ローマン: `"EB Garamond", Georgia, serif` (Web font: Google Fonts EB Garamond)

### 3.3 Spacing rhythm

| Token | Value | Usage |
|---|---|---|
| `--gap-section` | 80px (mobile: 56px) | Between top-level sections |
| `--gap-block` | 28–40px | Between blocks within a section |
| `--gap-paragraph` | 14px | Between paragraphs in body |
| `--container` | max-width 980px | Outer container |
| `--reading` | max-width 580px | Body text reading column |
| `--page-padding` | 64px (desktop) / 24px (mobile) | Container side padding |

### 3.4 Layout rules
- **Single column** as the dominant pattern. Multi-column only when content explicitly demands it (e.g., Benefits 3-category split on desktop).
- **Body width capped at ~580px** for readability.
- **Section dividers** are hairlines, not blocks. Use `--line` or a 1px navy under-rule on h-rows.
- **No cards.** Sections breathe via whitespace and typography, not boxes.
- **No icons** beyond simple typographic marks (✓, ×, —, ◆, ◇, ■).

### 3.5 Motion
- なし（初期実装）。スクロール体験を阻害しない。
- 必要なら、極めて控えめなフェードイン（200ms ease-out-quart）のみ。
- バウンス・エラスティック・パララックスは一切使わない。

---

## 4. Component patterns

### 4.1 Header (`<header>`)
```
左: [G&T] (Garamond, navy, 22px) + [Give & Take マネジメント] (Mincho, ink, 11px, vertical-aligned)
右: [☰] (menu, 18px ink)
下: 1px hairline divider (--line)
```
- 固定（sticky）にしない。スクロールに従って自然に流れる。
- メニューの中身は最小（Vision / Method / Profile / Contact など）— 初期はクリック動作なし、骨組みのみ。

### 4.2 Hero (Section 01)
```
[caption / 任意 small label]
[H1 Mincho — quote chars in navy]
[divider — 32px hairline navy, 36px vertical margin]
[Sub Mincho 19px]
```
- ロゴはヘッダー側で表示済み。Hero自体は文字のみ。
- 「『今は仕事に集中したい』と言いながら、…」の引用符『』は **navy色**で目立たせる。
- ページの最初の余白は大きめ（top padding 80–120px）。

### 4.3 Section pattern (繰り返し基本形)
```
[番号 row: <Numeral I.> + <italic eyebrow "Relationship Strategy">]
   ↑ 下に1pxの --line ハイラインを入れる
[H2 Mincho — 主張]
[Body Gothic — 段落]
[(任意) Pull quote — 2px left border navy + Mincho 17px]
[(任意) リスト — 後述のリストパターン]
```

### 4.4 List patterns (3つのバリエーション)

**a) Bullet + sub-explanation** (例: "Why this is not a brake", "After")
```
[Mincho h3 - 短い宣言文]
[Gothic body - 補足説明]
```
3項目を縦に並べ、各項目の間に12pxの空き。区切り線なし、空白で分ける。

**b) Check list** (例: CHECK LIST)
```
[✓ navy] [Mincho 16px - 1行]
```
7項目縦並び。チェックマークは navy、文字は ink。番号なし。

**c) Mistake / Wrong attempt** (例: MISTAKE)
```
[× navy small] [Mincho 16px - "正論で話せば、わかってもらえるはず"]
[Gothic 13px small - "→ 土台がないまま話すと、責められたように受け取られやすい"]
```
4項目縦並び。×印は薄めの navy。

**d) Benefits category** (例: BENEFITS - 3分類)
```
[◆/◇/■ navy + label "男性側のメリット"]
[Gothic body箇条書き 4項目]
```
シンボルでカテゴリを区別、本文は細字ゴシック。

### 4.5 Profile (Section 12)
```
[circular photo 200px] (左 / mobile では中央)
[Mincho 24px - "立花 由佳 / Yuka Tachibana"]
[Garamond italic 13px - "Yuka Tachibana"] (任意)
[Gothic 13px - 肩書 "一般社団法人 日本Give&Takeマネジメント協会 代表理事"]

[Mincho 19px - "経営者が、仕事と人生の両方に力を注げる関係性を整える。"]
[Gothic body - 経歴文]

[divider hairline]
[2列グリッド: ラベル / 内容]
登壇実績  | 講演・研修・セミナー登壇 500回以上
専門領域  | 第一印象 / 話し方 / 信頼構築 / 関係性設計 / コミュニケーション
経歴      | 東京都出身 / 聖心女子大学卒業 / 元フリーアナウンサー
```
写真は円形クロップ（border-radius: 50%）、200x200px、後から `images/yuka-tachibana.png` に差し替え。

### 4.6 CTA (Section 13: Closing)
```
[caption "Take the next step" - 任意]
[Mincho h2 - "話を、聞きにきてください。" など]
[outlined button - 1px ink border, 14px×28px padding, Gothic 13px label]
"問い合わせる →"
```
- ボタンは1個のみ。本ページ内の他の場所にはCTAを置かない。
- リンク先は最初は `mailto:` か外部フォーム。後から差し替え可。

### 4.7 Footer (Section 14)
```
[1px hairline divider]
[左: G&T - 迷いを晴らし、育める関係へ。]
[右: © 2026 一般社団法人 日本Give&Takeマネジメント協会 (任意)]
```
極小の余白で、軽く着地する。

---

## 5. Page architecture

ページは **Header (00) + 13個の主要セクション (01–13) + Footer (14) = 計15ブロック** で構成される。便宜上「14章」と呼ぶ場合もあるが、正確には Header と Footer を含めた15ブロック。各ブロックは下表の英語ラベルを `id` 属性に使用してアンカーリンク可能にする。

| # | English Label | 日本語headline (要約) | 主な要素 |
|---|---|---|---|
| 00 | Header | G&T / Give & Take マネジメント | ロゴ + メニュー |
| 01 | Hero / Hook | 『今は仕事に集中したい』と言いながら、心のどこかで何かが引っかかる経営者へ。 | 大Mincho hero + sub |
| 02 | Relationship Strategy | 仕事も、人生も、納得して選べる未来へ。 | 長文ナラティブ + 引用ブロック |
| 03 | (補完: Why not a brake) | 結婚や未来の話は、仕事を止めるためのものではありません。 | 3項目ベネフィット |
| 04 | After | なれる状態 — 迷いが晴れ、関係を育める | 3項目アウトカム |
| 05 | (補完: The real walls) | うまくいかない理由は、気持ちが足りないからではありません。 | 3壁の説明 |
| 06 | Check List | こんなことを感じていませんか？ | 7項目チェックリスト |
| 07 | Mistake | 自力で越えようとすると | 4つのよくある誤り |
| 08 | Why Change | 壁を越える順番がわかると、モヤモヤは少しずつ言葉になっていく。 | 5段階の変化 |
| 09 | Method | では、何をするのか — Give&Takeマネジメントという手法 | 手法の説明 |
| 10 | Benefits | 壁を越えた先の変化 | 男性側 / パートナー側 / ふたり |
| 11 | Vision | 迷いが減ると、仕事にも家庭にも力を注げる。 | 理想の未来 |
| 12 | Profile | 立花 由佳 | 写真 + 肩書 + 経歴 |
| 13 | Closing | 仕事も、結婚も、ひとりで抱え込まなくていい。 | 余韻 + 静かなCTA |
| 14 | Footer | Give&Take タグライン | 著作権 |

**英語ラベル**：原文にあるもの（02, 04, 06, 07, 08, 09, 10, 11, 12, 13）は原文ママ。補完（03, 05）は実装段階で立花さんに最終確認、または番号表記のみ（II., III.）に切り替える可能性あり。

---

## 6. Tech stack & deliverable

### 6.1 Stack
- **Static HTML + CSS**。フレームワーク不使用。
- Web font: **EB Garamond** を Google Fonts から読み込み（subsetted）。明朝とゴシックは OS 標準フォントスタック。
- JavaScript: 不使用（ハンバーガーメニューも初期は機能せず、形だけ）。
- Build step: 不要。`index.html` を直接開けば見える。

### 6.2 File structure
```
projects/give-take-management/
  index.html           # メインのLP
  styles.css           # スタイル分離（任意。embedも可）
  images/
    yuka-tachibana.png # 立花さんの円形プロフィール写真
  PRODUCT.md           # 本spec §1 (project context) と §2 (design principles) の要約 — Impeccable の `/teach` 出力フォーマットに準拠
  DESIGN.md            # 本spec §3 (design system) と §4 (component patterns) の要約 — Impeccable の `/document` 出力フォーマットに準拠
  README.md            # ローカル開発・ビルド方法（任意）
```

### 6.3 Hosting (TBD)
初期実装では決めない。実装後に：
- ローカル静的ファイル（共有不可）
- Vercel / Netlify (HTTPS, 独自ドメイン可)
- GitHub Pages

のいずれかから選ぶ。実装計画書（writing-plans）の段階で改めて議論。

---

## 7. Responsive

### 7.1 Breakpoints
- Desktop: ≥ 1024px
- Tablet: 640–1023px
- Mobile: < 640px

### 7.2 Responsive rules
- Container max-width 980px（ページ全体）と reading max-width 580px（本文）。
- 13.5px body は mobile では 14px に微増（読みやすさ確保）。
- Hero 30px → mobile 26px、H2 24px → mobile 21px。
- Profile photo: desktop 200px / mobile 160px。
- Benefits 3-category: desktop 3カラム / mobile 1カラム縦。
- Padding: desktop 64px / mobile 24px。

### 7.3 Tap targets (mobile)
ハンバーガーメニュー、CTAボタン、メールリンク：すべて最小 44×44px。

---

## 8. Accessibility & quality

- セマンティックHTML：`<header>`, `<main>`, `<article>`, `<section>`, `<h1>–<h3>`, `<blockquote>`, `<nav>`, `<footer>`
- 色コントラスト：ink (#1a1a1a) on paper (#ffffff) で 16:1 → AAA 余裕でパス。body (#444) on paper でも 9.7:1 → AAA。
- 画像 alt: profile photo に "立花 由佳 — 一般社団法人 日本Give&Takeマネジメント協会 代表理事"
- フォーカススタイル：CTAボタンに 2px navy outline（外側）。
- `lang="ja"` を `<html>` に明示。
- prefers-reduced-motion: 唯一あるかもしれないフェードを無効化。

---

## 9. Out of scope (初期実装)

- CMS / 動的コンテンツ
- 多ページナビゲーション（単一LP）
- 多言語（日本語のみ）
- 問い合わせフォームのバックエンド（CTAは `mailto:` または外部フォームURLに飛ばす）
- アナリティクス / ピクセル
- A/Bテスト
- ハンバーガーメニューの開閉動作（形だけ用意、機能は後付け）
- 会員ログイン / 課金導線
- ソーシャル共有ボタン

これらが必要になったら、次のフェーズで個別に検討する。

---

## 10. Implementation phasing

### Phase 1 (この spec の対象)
1. `projects/give-take-management/` ディレクトリ作成
2. PRODUCT.md / DESIGN.md（本specの要約）を Impeccable 用に配置
3. `index.html` 実装（14セクション、実コンテンツ全文、navy color、レスポンシブ）
4. `images/yuka-tachibana.png` のプレースホルダー（実画像を後で受領・配置）
5. ローカルブラウザで全セクション確認、レスポンシブ確認
6. **エラーチェック担当（別エージェント）に独立レビューを依頼**：反映漏れ・タイポ・崩れ・他ページへの影響なし確認
7. 誠司に最終確認

### Phase 2 (後日)
- 実プロフィール写真の差し替え
- 公開先（Vercel等）の決定とデプロイ
- お問い合わせ先（mailtoかフォームURL）の確定

---

## 11. Review checklist (誠司向け — レビュー時の観点)

このspecを読んで、以下を確認してください：

- [ ] **思想**：「装飾ではなく、構造とロジックで伝える」が貫かれているか
- [ ] **トーン**：「温かく親しみがある × 高級感」「男女に寄らない」が外れていないか
- [ ] **Anti-reference**：派手なグラデ／スクール感／情報商材／胡散臭さ／ベージュ が混入していないか
- [ ] **章構成**：14章の順番・統合・分割について意図と違うものがないか
- [ ] **英語ラベル**：補完したもの（03, 05）について、立花さんと最終確認するか、番号のみにするか
- [ ] **Out of scope**：含めないと明記したものに、本当は必要なものが混じっていないか
- [ ] **写真**：円形クロップ・200pxという扱いで違和感がないか

---

## 12. Open questions (実装計画書を書く前に確定したいもの)

1. **公開先（hosting）**：初期はローカルのみで十分か、Vercel等に上げるか？
2. **問い合わせ先**：CTAボタンのリンク先（mailto / 外部フォーム / その他）
3. **メニュー項目**：ハンバーガーの中身を実装段階で決めるか、初期は形だけか？
4. **EB Garamond**：Google Fonts 読み込みでOKか、self-hostedにするか？

これらは spec 承認後、writing-plans の段階で確定する。
