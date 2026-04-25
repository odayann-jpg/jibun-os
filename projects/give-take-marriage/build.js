const pptxgen = require("pptxgenjs");

const pres = new pptxgen();
pres.layout = "LAYOUT_16x9"; // 10" x 5.625"
pres.title = "結婚前 Give&Take マネジメント";
pres.author = "Give&Take Management";

// ===== Color Palette: Midnight Executive =====
const C = {
  navy: "1E2761",
  navyDeep: "0F1B47",
  navyDark: "151B3A",
  ice: "CADCFC",
  iceLight: "EAF1FF",
  charcoal: "1F2937",
  body: "374151",
  muted: "6B7280",
  border: "E5E7EB",
  surface: "F8FAFC",
  cardBg: "FAFBFD",
  white: "FFFFFF",
};

const FONT_H = "Yu Gothic";
const FONT_B = "Yu Gothic";

// ===== Helpers =====
function pageNumber(slide, n, total) {
  slide.addText(`${String(n).padStart(2, "0")} / ${String(total).padStart(2, "0")}`, {
    x: 8.7, y: 5.25, w: 1.1, h: 0.3,
    fontSize: 9, fontFace: FONT_B, color: C.muted, align: "right", margin: 0,
  });
}

function footerBrand(slide) {
  slide.addText("結婚前 Give&Take マネジメント", {
    x: 0.5, y: 5.25, w: 5, h: 0.3,
    fontSize: 9, fontFace: FONT_B, color: C.muted, align: "left", margin: 0,
    charSpacing: 2,
  });
}

function accentBar(slide, x = 0.5, y = 0.55, h = 0.45) {
  slide.addShape(pres.shapes.RECTANGLE, {
    x, y, w: 0.07, h, fill: { color: C.navy }, line: { type: "none" },
  });
}

function pageTitle(slide, text, sub) {
  accentBar(slide, 0.5, 0.55, 0.5);
  slide.addText(text, {
    x: 0.7, y: 0.45, w: 8.8, h: 0.55,
    fontSize: 26, fontFace: FONT_H, bold: true, color: C.charcoal, margin: 0,
  });
  if (sub) {
    slide.addText(sub, {
      x: 0.7, y: 1.0, w: 8.8, h: 0.35,
      fontSize: 12, fontFace: FONT_B, color: C.muted, margin: 0, charSpacing: 2,
    });
  }
}

const TOTAL = 14;

// ===========================================================
// Slide 1 — Title (Navy background)
// ===========================================================
{
  const s = pres.addSlide();
  s.background = { color: C.navyDeep };

  // Decorative thin lines
  s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: 0.5, w: 0.6, h: 0.03, fill: { color: C.ice }, line: { type: "none" } });
  s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: 5.1, w: 0.6, h: 0.03, fill: { color: C.ice }, line: { type: "none" } });

  s.addText("FOR MALE EXECUTIVES", {
    x: 0.5, y: 0.7, w: 6, h: 0.35,
    fontSize: 11, fontFace: FONT_B, color: C.ice, charSpacing: 6, margin: 0,
  });

  s.addText("結婚前", {
    x: 0.5, y: 1.4, w: 9, h: 0.65,
    fontSize: 26, fontFace: FONT_H, color: C.ice, margin: 0, charSpacing: 4,
  });

  s.addText("Give&Take マネジメント", {
    x: 0.5, y: 2.05, w: 9, h: 1.15,
    fontSize: 44, fontFace: FONT_H, bold: true, color: C.white, margin: 0, charSpacing: 2,
  });

  s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: 3.4, w: 0.5, h: 0.04, fill: { color: C.ice }, line: { type: "none" } });

  s.addText("経営者男性のための、関係性設計コンサル", {
    x: 0.5, y: 3.6, w: 9, h: 0.5,
    fontSize: 18, fontFace: FONT_H, color: C.white, margin: 0, charSpacing: 2,
  });

  s.addText("Pre-Marriage Relationship Design Consulting", {
    x: 0.5, y: 4.2, w: 9, h: 0.4,
    fontSize: 11, fontFace: FONT_B, italic: true, color: C.ice, margin: 0, charSpacing: 3,
  });
}

// ===========================================================
// Slide 2 — コンセプト
// ===========================================================
{
  const s = pres.addSlide();
  s.background = { color: C.white };
  pageTitle(s, "コンセプト", "CONCEPT");

  // Quote block
  s.addShape(pres.shapes.RECTANGLE, {
    x: 0.5, y: 1.7, w: 0.07, h: 1.4, fill: { color: C.navy }, line: { type: "none" },
  });
  s.addText([
    { text: "結婚前に整えるべきなのは、", options: { breakLine: true } },
    { text: "条件だけではなく、ふたりの未来と関係性です。" },
  ], {
    x: 0.8, y: 1.7, w: 8.7, h: 1.4,
    fontSize: 22, fontFace: FONT_H, bold: true, color: C.charcoal, margin: 0, paraSpaceAfter: 6,
  });

  // Body
  s.addText([
    { text: "経営者男性は、仕事ではロジックで物事を考えます。", options: { breakLine: true } },
    { text: "目的を決め、未来を描き、相手の求めるものを考え、問題が起きる前に先回りする。", options: { breakLine: true } },
    { text: "", options: { breakLine: true, fontSize: 6 } },
    { text: "しかし家庭やパートナーとの関係になると、近い存在だからこそ感情が強く出やすくなる。", options: { breakLine: true } },
    { text: "外ではロジックで信頼を築けるのに、家では感情に気づけず、信頼を削ってしまう。" },
  ], {
    x: 0.8, y: 3.3, w: 8.7, h: 1.6,
    fontSize: 13, fontFace: FONT_B, color: C.body, margin: 0, paraSpaceAfter: 3,
  });

  footerBrand(s);
  pageNumber(s, 2, TOTAL);
}

// ===========================================================
// Slide 3 — 一言で言うと
// ===========================================================
{
  const s = pres.addSlide();
  s.background = { color: C.white };
  pageTitle(s, "一言で言うと", "IN ONE SENTENCE");

  // Big card
  s.addShape(pres.shapes.RECTANGLE, {
    x: 0.5, y: 1.7, w: 9, h: 3.2,
    fill: { color: C.iceLight }, line: { type: "none" },
  });
  s.addShape(pres.shapes.RECTANGLE, {
    x: 0.5, y: 1.7, w: 0.08, h: 3.2,
    fill: { color: C.navy }, line: { type: "none" },
  });

  s.addText("結婚後にすれ違わないために、", {
    x: 0.9, y: 2.0, w: 8.4, h: 0.55,
    fontSize: 24, fontFace: FONT_H, bold: true, color: C.charcoal, margin: 0,
  });
  s.addText("自分の与え方・求め方を整理し、", {
    x: 0.9, y: 2.6, w: 8.4, h: 0.55,
    fontSize: 24, fontFace: FONT_H, bold: true, color: C.charcoal, margin: 0,
  });
  s.addText("相手が本当に幸せになる形と、", {
    x: 0.9, y: 3.2, w: 8.4, h: 0.55,
    fontSize: 24, fontFace: FONT_H, bold: true, color: C.charcoal, margin: 0,
  });
  s.addText("ふたりで目指す未来を設計するコンサル。", {
    x: 0.9, y: 3.8, w: 8.4, h: 0.55,
    fontSize: 24, fontFace: FONT_H, bold: true, color: C.navy, margin: 0,
  });

  footerBrand(s);
  pageNumber(s, 3, TOTAL);
}

// ===========================================================
// Slide 4 — なぜ経営者男性に必要か
// ===========================================================
{
  const s = pres.addSlide();
  s.background = { color: C.white };
  pageTitle(s, "なぜ経営者男性に必要なのか", "WHY EXECUTIVES NEED THIS");

  // Lead text
  s.addText("経営者は、仕事では多くのことを先回りしている。", {
    x: 0.7, y: 1.55, w: 8.8, h: 0.4,
    fontSize: 14, fontFace: FONT_B, color: C.body, margin: 0,
  });
  s.addText("売上、採用、資金繰り、契約、顧客対応、組織づくり。問題が起きる前に手を打つ重要性を知っている。", {
    x: 0.7, y: 1.95, w: 8.8, h: 0.4,
    fontSize: 12, fontFace: FONT_B, color: C.muted, margin: 0,
  });

  // 3 columns: misconceptions
  const items = [
    { t: "「好きで結婚する\nのだから大丈夫」" },
    { t: "「家族のために\n頑張れば伝わる」" },
    { t: "「お金に困らせ\nなければ大丈夫」" },
  ];
  const cardW = 2.85, cardH = 1.3, gap = 0.15, startX = 0.5;
  items.forEach((it, i) => {
    const x = startX + i * (cardW + gap);
    s.addShape(pres.shapes.RECTANGLE, {
      x, y: 2.6, w: cardW, h: cardH,
      fill: { color: C.surface }, line: { color: C.border, width: 0.75 },
    });
    s.addText(it.t, {
      x: x + 0.1, y: 2.65, w: cardW - 0.2, h: cardH - 0.1,
      fontSize: 14, fontFace: FONT_H, color: C.charcoal, align: "center", valign: "middle", margin: 0,
    });
  });

  // Bottom callout
  s.addShape(pres.shapes.RECTANGLE, {
    x: 0.5, y: 4.2, w: 9, h: 0.85,
    fill: { color: C.navyDeep }, line: { type: "none" },
  });
  s.addText("結婚後のすれ違いの多くは、愛情がないから起きるのではない。与え方・求め方・伝え方・目指す未来を設計していないから起きる。", {
    x: 0.7, y: 4.25, w: 8.6, h: 0.75,
    fontSize: 13, fontFace: FONT_H, bold: true, color: C.white, valign: "middle", margin: 0,
  });

  footerBrand(s);
  pageNumber(s, 4, TOTAL);
}

// ===========================================================
// Helper for failure pattern slides
// ===========================================================
function failureSlide(num, items, pageNo) {
  const s = pres.addSlide();
  s.background = { color: C.white };
  pageTitle(s, "経営者男性が結婚後に起こしやすい失敗", `${num} / 3`);

  const cardW = 4.35, cardH = 3.0, gap = 0.3, startX = 0.5;
  items.forEach((it, i) => {
    const x = startX + i * (cardW + gap);
    const y = 1.7;

    // Card
    s.addShape(pres.shapes.RECTANGLE, {
      x, y, w: cardW, h: cardH,
      fill: { color: C.cardBg }, line: { color: C.border, width: 0.75 },
    });
    // Accent
    s.addShape(pres.shapes.RECTANGLE, {
      x, y, w: cardW, h: 0.05,
      fill: { color: C.navy }, line: { type: "none" },
    });

    // Number badge (top-left of card header row)
    s.addShape(pres.shapes.OVAL, {
      x: x + 0.3, y: y + 0.3, w: 0.5, h: 0.5,
      fill: { color: C.navy }, line: { type: "none" },
    });
    s.addText(String(it.n), {
      x: x + 0.3, y: y + 0.3, w: 0.5, h: 0.5,
      fontSize: 16, fontFace: FONT_H, bold: true, color: C.white, align: "center", valign: "middle", margin: 0,
    });

    // Title (top-aligned to badge top, supports 1-2 lines)
    s.addText(it.title, {
      x: x + 0.95, y: y + 0.3, w: cardW - 1.1, h: 0.7,
      fontSize: 14, fontFace: FONT_H, bold: true, color: C.charcoal, valign: "top", margin: 0, paraSpaceAfter: 0,
    });

    // Divider line
    s.addShape(pres.shapes.LINE, {
      x: x + 0.3, y: y + 1.1, w: cardW - 0.6, h: 0,
      line: { color: C.border, width: 0.5 },
    });

    // Body
    s.addText(it.body, {
      x: x + 0.3, y: y + 1.2, w: cardW - 0.6, h: cardH - 1.35,
      fontSize: 12, fontFace: FONT_B, color: C.body, margin: 0, paraSpaceAfter: 4, valign: "top",
    });
  });

  footerBrand(s);
  pageNumber(s, pageNo, TOTAL);
}

// ===========================================================
// Slide 5 — 失敗 ①②
// ===========================================================
failureSlide("1", [
  {
    n: 1, title: "外ではロジック10、家では感情10",
    body: "仕事では冷静に判断できるのに、近い関係だからこそ感情が強く出る。「わかってほしい」「感謝してほしい」「認めてほしい」が、本人はロジックのつもりで正論・圧・不機嫌として出てしまう。",
  },
  {
    n: 2, title: "稼ぐことが最大のGiveだと思ってしまう",
    body: "責任を背負って働く分、「これだけやっている」と思いやすい。でもパートナーが本当に求めているのは、安心・大切にされる実感・一緒に未来を考えてくれること。Giveの形がズレている。",
  },
], 5);

// ===========================================================
// Slide 6 — 失敗 ③④
// ===========================================================
failureSlide("2", [
  {
    n: 3, title: "近い人ほど後回しにしてしまう",
    body: "外の人には丁寧なのに、パートナーには「わかってくれているはず」で連絡や説明を省いてしまう。愛情がないのではなく、安心している相手ほど雑になりやすい。信頼は静かに削れていく。",
  },
  {
    n: 4, title: "「してあげている」が不満に変わる",
    body: "生活費、仕事、食事、旅行、プレゼント。その裏に「わかってほしい」「感謝してほしい」があると、GiveがTakeに変わる。本人は頑張りのつもり、相手は押しつけと感じてしまう。",
  },
], 6);

// ===========================================================
// Slide 7 — 失敗 ⑤⑥
// ===========================================================
failureSlide("3", [
  {
    n: 5, title: "家庭でも社長のままでいようとしてしまう",
    body: "決める・指示する・責任を取るは仕事の強み。でも家庭でも“決める人”でいることが正解とは限らない。正論なのに関係が悪くなるのは、関係性の前提がズレているから。家庭は会社ではない。",
  },
  {
    n: 6, title: "結婚したら自然にうまくいくと思ってしまう",
    body: "会社では仕組みを作るのに、家庭では「結婚すれば自然と」と考えがち。時間・お金・親・子・仕事の優先順位――設計しないまま進めば、小さなズレが積み重なる。結婚は運用の始まり。",
  },
], 7);

// ===========================================================
// Slide 8 — 問題の本質（4つ・2x2）
// ===========================================================
{
  const s = pres.addSlide();
  s.background = { color: C.white };
  pageTitle(s, "問題の本質", "ROOT CAUSES");

  s.addText("経営者男性の結婚後の失敗は、愛情がないから起きるのではなく、多くの場合この4つから起きる。", {
    x: 0.7, y: 1.5, w: 8.8, h: 0.35,
    fontSize: 12, fontFace: FONT_B, color: C.muted, margin: 0,
  });

  const items = [
    { n: "01", title: "自分の感情に気づいていない",
      body: "外ではロジックで動いているため家でも自分は冷静だと思ってしまう。実際は「わかってほしい」「認めてほしい」が強く出ている。" },
    { n: "02", title: "自分が与えたいものを与えている",
      body: "稼ぐ・守る・連れて行く・買ってあげる。それ自体は悪くないが、相手が本当に欲しいものとズレていると、信頼として積み上がらない。" },
    { n: "03", title: "求めているのに、求めていると言えない",
      body: "「感謝してほしい」「支えてほしい」「味方でいてほしい」を素直に言えず、正論・不機嫌・沈黙・圧として出てしまう。" },
    { n: "04", title: "ふたりの未来が言語化されていない",
      body: "どんな夫婦・家庭でいたいか、何を大切にするか。曖昧なまま結婚すると、お互い別々のゴールを持ったまま生活が始まる。" },
  ];

  const cardW = 4.4, cardH = 1.55, gapX = 0.2, gapY = 0.15, startX = 0.5, startY = 1.95;
  items.forEach((it, i) => {
    const col = i % 2, row = Math.floor(i / 2);
    const x = startX + col * (cardW + gapX);
    const y = startY + row * (cardH + gapY);
    s.addShape(pres.shapes.RECTANGLE, {
      x, y, w: cardW, h: cardH,
      fill: { color: C.cardBg }, line: { color: C.border, width: 0.75 },
    });
    s.addShape(pres.shapes.RECTANGLE, {
      x, y, w: 0.07, h: cardH, fill: { color: C.navy }, line: { type: "none" },
    });
    s.addText(it.n, {
      x: x + 0.25, y: y + 0.18, w: 0.7, h: 0.3,
      fontSize: 11, fontFace: FONT_B, bold: true, color: C.navy, charSpacing: 2, margin: 0,
    });
    s.addText(it.title, {
      x: x + 0.25, y: y + 0.48, w: cardW - 0.45, h: 0.4,
      fontSize: 14, fontFace: FONT_H, bold: true, color: C.charcoal, margin: 0,
    });
    s.addText(it.body, {
      x: x + 0.25, y: y + 0.88, w: cardW - 0.45, h: cardH - 0.95,
      fontSize: 10.5, fontFace: FONT_B, color: C.body, margin: 0, paraSpaceAfter: 2,
    });
  });

  footerBrand(s);
  pageNumber(s, 8, TOTAL);
}

// ===========================================================
// Slide 9 — 解決策①
// ===========================================================
{
  const s = pres.addSlide();
  s.background = { color: C.white };
  pageTitle(s, "解決策 ①", "SOLUTION 01");

  s.addText("相手が本当はどうなったら幸せかを考える", {
    x: 0.7, y: 1.5, w: 8.8, h: 0.6,
    fontSize: 24, fontFace: FONT_H, bold: true, color: C.navy, margin: 0,
  });

  s.addText("自分が与えたいものを与えるのではなく、相手が幸せになる形で与える。", {
    x: 0.7, y: 2.15, w: 8.8, h: 0.4,
    fontSize: 14, fontFace: FONT_H, color: C.charcoal, margin: 0,
  });

  // 4 question chips
  const qs = [
    "相手は、何があると安心するのか",
    "どんな関わり方をされると、大切にされていると感じるのか",
    "何を言葉にしてもらえると満たされるのか",
    "どんな未来を、一緒に見たいのか",
  ];
  qs.forEach((q, i) => {
    const y = 2.85 + i * 0.5;
    s.addShape(pres.shapes.RECTANGLE, {
      x: 0.7, y, w: 0.04, h: 0.35, fill: { color: C.navy }, line: { type: "none" },
    });
    s.addText(q, {
      x: 0.85, y: y - 0.04, w: 8.2, h: 0.45,
      fontSize: 13, fontFace: FONT_B, color: C.body, valign: "middle", margin: 0,
    });
  });

  footerBrand(s);
  pageNumber(s, 9, TOTAL);
}

// ===========================================================
// Slide 10 — 解決策②③（2列）
// ===========================================================
{
  const s = pres.addSlide();
  s.background = { color: C.white };
  pageTitle(s, "解決策 ② ③", "SOLUTION 02 / 03");

  const cards = [
    {
      label: "02",
      title: "お互いの未来を設計する",
      lead: "ルール決めではなく、ふたりの人生の設計図を共有する。",
      items: [
        "どんな夫婦・家庭でいたいか",
        "仕事と家庭をどう両立するか",
        "忙しい時期にどう支え合うか",
        "お金・子ども・親との関係",
        "お互いがどんな状態だと幸せか",
      ],
    },
    {
      label: "03",
      title: "戻る場所としての関係性を言葉にする",
      lead: "問題が起きたときに戻れる、ふたりの共通認識をつくる。",
      items: [
        "支え合い、応援し合う関係",
        "対等に話し合える関係",
        "弱さを安心して出せる関係",
        "感謝を言葉にできる関係",
        "敵にならず、人生のチームでいる",
      ],
    },
  ];

  const cardW = 4.4, cardH = 3.55, gap = 0.2, startX = 0.5, startY = 1.55;
  cards.forEach((c, i) => {
    const x = startX + i * (cardW + gap);
    s.addShape(pres.shapes.RECTANGLE, {
      x, y: startY, w: cardW, h: cardH,
      fill: { color: C.cardBg }, line: { color: C.border, width: 0.75 },
    });
    s.addShape(pres.shapes.RECTANGLE, {
      x, y: startY, w: cardW, h: 0.05, fill: { color: C.navy }, line: { type: "none" },
    });
    s.addText(c.label, {
      x: x + 0.3, y: startY + 0.2, w: 1, h: 0.3,
      fontSize: 11, fontFace: FONT_B, bold: true, color: C.navy, charSpacing: 2, margin: 0,
    });
    s.addText(c.title, {
      x: x + 0.3, y: startY + 0.5, w: cardW - 0.6, h: 0.5,
      fontSize: 16, fontFace: FONT_H, bold: true, color: C.charcoal, margin: 0,
    });
    s.addText(c.lead, {
      x: x + 0.3, y: startY + 1.05, w: cardW - 0.6, h: 0.45,
      fontSize: 11.5, fontFace: FONT_B, color: C.muted, margin: 0,
    });
    const bullets = c.items.map((t, idx) => ({
      text: t, options: { bullet: { code: "25A0" }, breakLine: idx < c.items.length - 1 },
    }));
    s.addText(bullets, {
      x: x + 0.3, y: startY + 1.65, w: cardW - 0.6, h: cardH - 1.7,
      fontSize: 11.5, fontFace: FONT_B, color: C.body, margin: 0, paraSpaceAfter: 4,
    });
  });

  footerBrand(s);
  pageNumber(s, 10, TOTAL);
}

// ===========================================================
// Slide 11 — コンサルで扱う5つの内容
// ===========================================================
{
  const s = pres.addSlide();
  s.background = { color: C.white };
  pageTitle(s, "コンサルで扱う5つの内容", "WHAT WE COVER");

  const items = [
    { n: "01", t: "結婚後に起きやすい失敗の理解", d: "経営者男性が陥りやすいパターンを見える化し、まず自覚する。" },
    { n: "02", t: "自分の Give&Take を整理する", d: "与えているつもりの裏にある「求め」に気づき、言葉に変える。" },
    { n: "03", t: "相手の幸せの形を理解する", d: "相手が本当に受け取りたいものは何か。届くGiveに整える。" },
    { n: "04", t: "ふたりの未来を設計する", d: "仕事のビジョンのように、結婚前にふたりの未来を言語化する。" },
    { n: "05", t: "戻る場所としての関係性をつくる", d: "細かいルールではなく、すれ違っても戻れる共通認識を持つ。" },
  ];

  const startY = 1.55;
  const rowH = 0.66;
  items.forEach((it, i) => {
    const y = startY + i * rowH;
    // Number
    s.addShape(pres.shapes.RECTANGLE, {
      x: 0.5, y: y, w: 0.85, h: 0.55,
      fill: { color: C.navy }, line: { type: "none" },
    });
    s.addText(it.n, {
      x: 0.5, y: y, w: 0.85, h: 0.55,
      fontSize: 16, fontFace: FONT_H, bold: true, color: C.white, align: "center", valign: "middle", margin: 0, charSpacing: 1,
    });
    // Title
    s.addText(it.t, {
      x: 1.55, y: y, w: 4.2, h: 0.55,
      fontSize: 15, fontFace: FONT_H, bold: true, color: C.charcoal, valign: "middle", margin: 0,
    });
    // Description
    s.addText(it.d, {
      x: 5.85, y: y, w: 3.7, h: 0.55,
      fontSize: 11.5, fontFace: FONT_B, color: C.body, valign: "middle", margin: 0,
    });
    // Divider
    if (i < items.length - 1) {
      s.addShape(pres.shapes.LINE, {
        x: 0.5, y: y + 0.6, w: 9, h: 0,
        line: { color: C.border, width: 0.5 },
      });
    }
  });

  footerBrand(s);
  pageNumber(s, 11, TOTAL);
}

// ===========================================================
// Slide 12 — メリット（3列）
// ===========================================================
{
  const s = pres.addSlide();
  s.background = { color: C.white };
  pageTitle(s, "メリット", "BENEFITS");

  const cols = [
    {
      label: "FOR HIM", title: "男性側",
      items: [
        "すれ違いを未然に防げる",
        "自分の感情・期待に気づける",
        "与えても伝わらないを減らせる",
        "家庭での圧・沈黙が減る",
        "仕事と家庭の切替が上手に",
        "長く信頼されやすくなる",
      ],
    },
    {
      label: "FOR HER", title: "パートナー側",
      items: [
        "大切にされる実感を持てる",
        "不安を事前に共有できる",
        "彼の考え・感情を理解しやすい",
        "将来を一緒に話せる安心感",
        "自分の幸せを言葉にできる",
        "ひとりで我慢する結婚にならない",
      ],
    },
    {
      label: "FOR US", title: "ふたり",
      items: [
        "価値観のズレを早く発見",
        "問題時に戻る場所がある",
        "目指す未来が明確になる",
        "感情的な衝突を減らせる",
        "信頼を積み上げ続けられる",
        "敵にならず、チームで進める",
      ],
    },
  ];

  const cardW = 2.95, cardH = 3.4, gap = 0.13, startX = 0.5, startY = 1.55;
  cols.forEach((c, i) => {
    const x = startX + i * (cardW + gap);
    s.addShape(pres.shapes.RECTANGLE, {
      x, y: startY, w: cardW, h: cardH,
      fill: { color: C.cardBg }, line: { color: C.border, width: 0.75 },
    });
    s.addShape(pres.shapes.RECTANGLE, {
      x, y: startY, w: cardW, h: 0.5, fill: { color: C.navyDeep }, line: { type: "none" },
    });
    s.addText(c.label, {
      x: x + 0.2, y: startY + 0.06, w: cardW - 0.4, h: 0.2,
      fontSize: 9, fontFace: FONT_B, bold: true, color: C.ice, charSpacing: 4, margin: 0,
    });
    s.addText(c.title, {
      x: x + 0.2, y: startY + 0.22, w: cardW - 0.4, h: 0.32,
      fontSize: 16, fontFace: FONT_H, bold: true, color: C.white, margin: 0,
    });

    const bullets = c.items.map((t, idx) => ({
      text: t,
      options: { bullet: { code: "25A0" }, breakLine: idx < c.items.length - 1 },
    }));
    s.addText(bullets, {
      x: x + 0.25, y: startY + 0.75, w: cardW - 0.5, h: cardH - 0.85,
      fontSize: 11, fontFace: FONT_B, color: C.body, margin: 4, paraSpaceAfter: 5, valign: "top",
    });
  });

  footerBrand(s);
  pageNumber(s, 12, TOTAL);
}

// ===========================================================
// Slide 13 — キーメッセージ (Navy bg)
// ===========================================================
{
  const s = pres.addSlide();
  s.background = { color: C.navyDeep };

  s.addText("KEY MESSAGE", {
    x: 0.5, y: 0.7, w: 9, h: 0.35,
    fontSize: 11, fontFace: FONT_B, color: C.ice, charSpacing: 6, margin: 0,
  });

  // Quote marks accent
  s.addShape(pres.shapes.RECTANGLE, {
    x: 0.5, y: 1.5, w: 0.6, h: 0.04, fill: { color: C.ice }, line: { type: "none" },
  });

  s.addText([
    { text: "外ではロジックで成果を出す人ほど、", options: { breakLine: true } },
    { text: "家庭では感情の扱い方で", options: { breakLine: true } },
    { text: "つまずくことがある。" },
  ], {
    x: 0.5, y: 1.85, w: 9, h: 1.85,
    fontSize: 30, fontFace: FONT_H, bold: true, color: C.white, margin: 0, paraSpaceAfter: 6,
  });

  s.addShape(pres.shapes.RECTANGLE, {
    x: 0.5, y: 3.85, w: 0.6, h: 0.04, fill: { color: C.ice }, line: { type: "none" },
  });

  s.addText("結婚後に崩れるのは、愛情ではなく、設計かもしれない。", {
    x: 0.5, y: 4.1, w: 9, h: 0.5,
    fontSize: 16, fontFace: FONT_H, italic: true, color: C.ice, margin: 0,
  });
}

// ===========================================================
// Slide 14 — CTA / 締め
// ===========================================================
{
  const s = pres.addSlide();
  s.background = { color: C.navyDeep };

  s.addShape(pres.shapes.RECTANGLE, { x: 0.5, y: 0.5, w: 0.6, h: 0.03, fill: { color: C.ice }, line: { type: "none" } });

  s.addText("CLOSING", {
    x: 0.5, y: 0.7, w: 9, h: 0.35,
    fontSize: 11, fontFace: FONT_B, color: C.ice, charSpacing: 6, margin: 0,
  });

  s.addText([
    { text: "経営者としての先回りを、", options: { breakLine: true } },
    { text: "一番大切な人との関係にも。" },
  ], {
    x: 0.5, y: 1.5, w: 9, h: 1.8,
    fontSize: 36, fontFace: FONT_H, bold: true, color: C.white, margin: 0, paraSpaceAfter: 6,
  });

  s.addShape(pres.shapes.RECTANGLE, {
    x: 0.5, y: 3.5, w: 9, h: 0.04, fill: { color: C.ice, transparency: 60 }, line: { type: "none" },
  });

  s.addText("結婚前 Give&Take マネジメント", {
    x: 0.5, y: 3.75, w: 9, h: 0.5,
    fontSize: 22, fontFace: FONT_H, bold: true, color: C.ice, margin: 0, charSpacing: 2,
  });
  s.addText("仕事ではできている信頼構築を、一番大切な人との関係にも活かす。", {
    x: 0.5, y: 4.3, w: 9, h: 0.4,
    fontSize: 13, fontFace: FONT_B, color: C.white, margin: 0,
  });
  s.addText("Pre-Marriage Relationship Design Consulting for Male Executives", {
    x: 0.5, y: 4.8, w: 9, h: 0.35,
    fontSize: 10, fontFace: FONT_B, italic: true, color: C.ice, charSpacing: 2, margin: 0,
  });
}

// ===========================================================
pres.writeFile({ fileName: "結婚前GiveTakeマネジメント.pptx" })
  .then((fn) => console.log("Wrote: " + fn));
