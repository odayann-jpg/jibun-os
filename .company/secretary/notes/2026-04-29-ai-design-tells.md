# AIっぽいデザインの兆候まとめ — Web/LP・スライド版

作成日: 2026-04-29
目的: 自社の制作物（LP・営業資料・社内提案・プロダクトUI）から「AI生成っぽさ」を抜き、ブランド固有の手触りに仕上げるための判断軸として保存。

---

## Part 1: ウェブサイト / LP / アプリ UI 編

### 1-1. ビジュアル・画像（AI画像）の兆候

- **手・指の破綻**: 指の本数・関節の曲がり方が不自然。物を握るシーンで特に崩れる
- **目の不自然さ**: 左右の瞳孔・視線・catchlight（瞳の反射光）の不一致
- **プラスチック肌**: 毛穴・産毛が消えロウ人形化
- **過剰な対称性**: 数学的に整いすぎ、生活感のあるノイズが消える
- **背景の論理崩壊**: 棚の線・看板の文字（読めない記号）・影の方向矛盾
- **髪の描写**: 一本一本ではなく塊で描かれる
- **均質ライティング**: 全方位から光が当たる物理的に不可能な照明

### 1-2. ウェブサイト/LPデザイン全体の兆候

- **紫→ピンク／紫→青のグラデ**（v0/Lovable/Bolt のデフォルト）
- **ガラスモーフィズム**＋ダーク背景＋ネオンアクセント＋浮かぶカード＋背景グラデオーブ（v0美学・Cursor美学）
- **shadcn/uiのデフォルトトーンそのまま**（オフホワイト背景）
- **テンプレ構成**: Hero → ロゴ帯 → Features 3-6カード → How it works 3ステップ → Testimonial → Pricing 3列 → FAQ → Final CTA → Footer
- **取って付けたTrust Badge**（根拠不明の数字、グレーアウトのロゴ帯）

### 1-3. タイポグラフィの兆候

- **Inter / Geist / Manrope への偏り**
- **サイズ階層が単調**（H1=48 / H2=32 / 本文=16 の3段階のみ）
- **ウェイトのコントラスト不足**（全部Medium）
- **行間広すぎ・字間デフォルト**
- **見出しの長さが揃いすぎ**（どれも2行に収まる）

### 1-4. マイクロコピー・本文の兆候

- **emダッシュ（—）の多用**
- **頻出語**: delve / tapestry / elevate / seamlessly / revolutionize / cutting-edge → 日本語訳で「シームレスに」「革新的な」「次のレベルへ」「圧倒的な」
- **接続詞テンプレ**: 「さらに」「現代のビジネスにおいて」
- **過剰な箇条書き**（段落で書ける内容を3点リストに分解）
- **三点リズムの濫用**（「速く、シンプルに、美しく」など三語並列）
- **空虚な価値訴求**（主語入れ替えで通用する文）
- **見出し冒頭に絵文字を機械的に付与**
- **英語直訳調**（「私たちは〜を信じています」）

### 1-5. UIコンポーネントの兆候

- **角丸 `rounded-2xl` / `rounded-xl` がすべてに均一**
- **ソフトシャドウ全カード同強度**（光源方向なし）
- **アイコンが Lucide / Heroicons のアウトライン一択**（線幅 1.5px 固定）
- **「黒地+白文字+角丸+矢印」ボタン**（Vercel/Linear的）
- **ホバーが全部 `hover:opacity-80` か `hover:scale-105`**
- **空状態テンプレ**（中央イラスト + "No items yet" + ボタン）
- **エラー表示の単調さ**（赤枠線+赤文字+三角アイコンのみ）
- **ダークモードが「単に色を反転」**（純黒背景+ネオン）
- **ガラスモーフィズム blur 値が過剰**

### 1-6. 構造・情報設計の兆候

- **セクション数が判で押したように6〜8**
- **CTAが全部「Get Started Free」「Book a demo」**
- **Pricingが必ず3段組み**（Free / Pro中央ハイライト / Enterprise "Contact us"）
- **FAQが4〜6項目の汎用質問**
- **Testimonial 3カード**（円アバター+1文+5つ星、出典なし）
- **About / 会社の物語の欠落**
- **画像がストック写真 or AI画像でブランド固有の世界観なし**

---

## Part 2: スライド（PowerPoint / Keynote / Gamma / Beautiful.ai / Tome / Copilot / Canva AI / Gemini）編

### 2-1. レイアウト・構成

- **全スライドが「タイトル+3カラム」または「アイコン+見出し+1文」の繰り返し**
- **3列カードグリッドの偏愛**
- **すべて左右対称・中央揃え**でジャンプ率なし
- **冗長な定型ページ**（Agenda / Overview / Key Takeaways / Thank you）
- **Tome特有**: 縦長スクロール式カード、印刷で間延び
- **Gamma特有**: カードドキュメント構造でフリーレイアウトが効かない

### 2-2. タイポグラフィ・テキスト量

- **見出し・本文・キャプションが全部同じ書体・同じウェイト**
- **Inter / Manrope / DM Sans / Plus Jakarta Sans への偏り**
- **見出しが説明文化**（長すぎ）
- **箇条書きが必ず3点・体言止め揃え**（「効率化」「最適化」「自動化」）
- **1スライドに文字を詰め込みすぎ**
- **太字・色文字・下線の同時多用で強調が崩壊**

### 2-3. 配色・ビジュアルトーン

- **紫〜青のグラデ**（indigo-500 → violet-500）
- **ダーク背景 + ネオン/シアン**（Tomeデフォルト）
- **glassmorphism**
- **Gammaデフォルト系**: 淡いベージュ / グレー + 紫アクセント
- **ブランドカラー未反映**でツールデフォルトが出る
- **影が全カードで同じ**

### 2-4. 画像・図解・アイコン

- **アイコンが Lucide / Phosphor / Heroicons の1セット均質**
- **「角丸スクエアにアイコン1個 + 見出し」のフィーチャーカード**
- **AI生成画像の手・指・文字破綻、人物プラスチック顔**
- **AIイラストと実写ストックの混在でトンマナ崩壊**
- **図解がフローチャート・ベン図・3円交差・ピラミッドの4種に偏る**
- **見出しに絵文字付与（Gamma特有）**: 「🚀 成長戦略」「💡 アイデア」「📊 データ」

### 2-5. データ・グラフ（最も信用を失う領域）

- **数字が「ちょうど良すぎる」**（73% / 85% / 3.2倍など語呂のいい数字）
- **データソース・調査年・サンプルサイズの欠落**
- **軸ラベル・単位の欠落**
- **棒グラフが3〜4本に揃う**
- **円グラフ多用で凡例とラベルが重複**
- **「○○%向上」「○○倍に成長」だけで根拠なし**
- **市場規模（TAM/SAM/SOM）にハルシネーション混入**
- **ホッケースティック型成長予測**がコスト構造の言及なく描かれる

### 2-6. コピー・本文

- **抽象動詞オンパレード**: Empower / Unlock / Transform / Elevate / Seamless / Streamline / Leverage / Synergy
- **日本語直訳調**: 「〜を実現する」「〜を加速させる」「〜をシームレスに」「〜を最適化」「〜を変革」
- **冒頭の枕詞が定型**: 「変化の激しい現代において」「DXが進む今」「不確実性の高い時代に」
- **箇条書きが体言止めの3点セット**
- **em dash の不自然な多用**
- **同じ主張が各スライドに微妙に言い換わって再登場**
- **トーンが急に変わる**（複数プロンプトの継ぎ接ぎ痕）
- **「お客様の声」がない／あっても抽象的**

### 2-7. 構造・ストーリー

- **全スライドが「主張 + 3つの理由 + CTA」の同じ型**
- **Problem → Solution → Benefit → CTA の単調な流れ**
- **スライド間の橋渡しが断絶**
- **固有の物語・顧客事例・失敗エピソードの欠如**（成功した未来の話だけ）
- **Q&A・付録・参考文献・脚注の欠落**
- **意思決定者の質問に答える構造になっていない**（「なぜ今？」「なぜあなたたち？」「なぜこの価格？」が抜ける）

### 2-8. アニメーション・トランジション

- **全スライド同じフェード・スライドイン**（Gammaデフォルト）
- **PowerPoint Morphの多用**
- **箇条書き3点が等間隔フェード**
- **過剰装飾アニメ**（バウンス・回転・拡大縮小の混在）
- **Tomeはスクロール前提でPDF化すると間延び**

### 2-9. ツール固有の癖（識別チェックリスト）

| ツール | 識別サイン |
|---|---|
| Gamma | カード型、絵文字付き見出し、淡いベージュ/グレー、大きい角丸カード、Inter系、フッター "Made with Gamma" |
| Beautiful.ai | Smart Slideの固定テンプレ、要素が動かせない見た目、フラット色面 |
| Tome | 縦長カード、ダーク+白文字、AIイラスト多用、印刷で間延び |
| Copilot for PowerPoint | Officeデフォルトテーマ流用、テキストボックス浮き、Stock Image過剰 |
| Canva Magic Design | Pexels/Pixabay汎用ストック、強い装飾、文体に場違いな引用混入 |
| Google Slides Gemini | Material系角丸+穏やかな配色、Roboto、個性が薄い |

---

## Part 3: 「AIっぽさ」を抜くための判断軸（実務用チェックリスト）

制作物が完成したら、以下の問いを自分に投げる。

1. **主語と業種を入れ替えてもこのコピーは通用するか？** → Yesなら「AIベージュ」（無難で誰にも刺さらない安全な語に収束した状態）
2. **このページ／資料の構造は競合と何が違うか？** → 答えられないならテンプレ依存
3. **色・フォント・角丸R値・アイコンに、ブランド固有の意思決定があるか？** → デフォルトのままならAI感が出る
4. **物語・固有名詞・固有の数字・固有の場所が含まれているか？** → 抽象的な美辞麗句だけなら警戒
5. **数字には出典・年・サンプルサイズが付いているか？** → 数字にハルシネーションが混じれば一発で信用を失う
6. **失敗・葛藤・不確実性が1箇所以上で語られているか？** → AIは平均化でこの部分を消す
7. **見出しに「Empower / Unlock / Transform / シームレス / 最適化 / 革新 / 変革」が入っていないか？** → 入っていたら一括検索して削る
8. **3列カード・左右対称・1スライド3点が連続していないか？** → 連続していたら割る
9. **紫〜青グラデ・Inter・Lucideアイコンに頼っていないか？** → ブランドに置き換える
10. **付録・脚注・FAQが付いているか？** → AIは補足を弱く扱う

---

## 主な出典

### Web/LP編
- [No More Purple](https://nomorepurple.com/)
- [Dark Mode Design That Doesn't Look AI - DEV](https://dev.to/raxxostudios/dark-mode-design-that-doesnt-look-ai-2cn3)
- [ChatGPT Hyphen - Rolling Stone](https://www.rollingstone.com/culture/culture-features/chatgpt-hypen-em-dash-ai-writing-1235314945/)
- [How to Spot AI-Generated Images - WhichOneIs](https://whichoneis.ai/blog/how-to-spot-ai-generated-images)
- [Cloud Retouch: Fixing AI Hand Artifacts](https://www.cloudretouch.com/fixing-ai-hand-artifacts-photo-retouching/)
- [Branding in the Age of AI - Design Etiquette](https://www.designetiquette.com/standing-out-in-ai-sameness/)
- [AI Is Flattening B2B Storytelling - Prologue Stories](https://www.prologuestories.com/blog/the-photocopy-problem-why-ai-is-flattening-b2b-storytelling)

### スライド編
- [Why Your AI Presentations Look AI-Generated - Llemental](https://llemental.com/posts/why-ai-presentations-look-ai-generated)
- [Why Your AI-Generated Slides Look Generic - Winning Presentations](https://winningpresentations.com/ai-generated-slides-look-generic/)
- [The 7 Deadly PowerPoint Copilot Mistakes](https://winningpresentations.com/powerpoint-copilot-mistakes/)
- [How to Check if a PowerPoint Was Made by AI - Presenti](https://presenti.ai/blog/check-ppt-for-ai/)
- [Wikipedia: Signs of AI writing](https://en.wikipedia.org/wiki/Wikipedia:Signs_of_AI_writing)
- [Common Mistakes Founders Make With AI Pitch Decks](https://www.codeventures.com/blog/common-mistakes-founders-make-with-ai-pitch-decks/)
- [We Tested 11 AI Pitch Deck Generators - Reprezent](https://reprezent.us/en/blog/we-tested-11-ai-pitch-deck-generators-so-you-dont-have-to)
- [Your AI Presentations Look Great (but say nothing) - Jeff Su](https://www.jeffsu.org/your-ai-presentations-look-great-but-say-nothing/)
