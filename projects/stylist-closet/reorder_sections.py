#!/usr/bin/env python3
"""
- 重複セクション除去・順序修正
- デザインを清潔感のある白ベースに変更
- ヒーロー高さ縮小
- LINE過剰訴求を削減
- 強み・選ばれる理由・pain-answer削除
"""
import re, shutil

shutil.copy('kaitori.html', 'kaitori.html.bak')

with open('kaitori.html', 'r', encoding='utf-8') as f:
    html = f.read()

# ── セクション切り出し（最初の出現のみ） ──────────────────
def get_first(marker):
    s = html.find(marker)
    if s == -1: return ''
    e = html.find('</section>', s) + len('</section>')
    while e < len(html) and html[e] == '\n': e += 1
    return html[s:e]

def get_last(marker):
    s = html.rfind(marker)
    if s == -1: return ''
    e = html.find('</section>', s) + len('</section>')
    while e < len(html) and html[e] == '\n': e += 1
    return html[s:e]

HERO      = get_first('<!-- HERO -->')
ABOUT     = get_first('<!-- ABOUT -->')
CASES     = get_first('<!-- CASES -->')
PAIN      = get_first('<!-- PAIN POINTS -->')
FLOW      = get_first('<!-- FLOW -->')
INSTAGRAM = get_first('<!-- INSTAGRAM -->')
BRANDS    = get_first('<!-- BRANDS -->')
VOICES    = get_first('<!-- VOICES -->')
FAQ       = get_first('<!-- FAQ -->')
STYLISTS  = get_first('<!-- STYLISTS -->')
OMOI      = get_last('<!-- OMOI -->')  # 後ろのものが正しい位置

MAP_S = html.find('<section class="map-section"')
MAP_E = html.find('</section>', MAP_S) + len('</section>')
while MAP_E < len(html) and html[MAP_E] == '\n': MAP_E += 1
MAP = html[MAP_S:MAP_E]

CTA = get_first('<!-- FINAL CTA -->')

TR_S = html.find('<div class="tag-row">')
TR_E = html.find('<hr class="divider" />')
TAGROW = html[TR_S:TR_E]

HEAD_END = html.find('<!-- NAV -->')
HEAD = html[:HEAD_END]
NAV_S, NAV_E = html.find('<!-- NAV -->'), html.find('<!-- HERO -->')
NAV = html[NAV_S:NAV_E]
FT_S = html.find('<!-- FOOTER -->')
FOOTER = html[FT_S:]

# ── PAIN: pain-answer ブロック削除 ───────────────────────
pa_s = PAIN.find('<div class="pain-answer reveal">')
if pa_s != -1:
    PAIN = PAIN[:pa_s] + '    </div>\n  </section>\n'

# ── CASES: スワイプヒント更新 ─────────────────────────────
CASES = CASES.replace(
    '<p class="section-sub">実際の買取価格をご参考に</p>',
    '<p class="section-sub">← スワイプして確認</p>'
)

# ── STORE PHOTOS: 横スクロール ─────────────────────────────
STORE = '''  <!-- STORE PHOTOS -->
  <section class="store-photos" id="store">
    <div class="wrap">
      <div class="sec-header reveal">
        <div class="sec-header__left">
          <span class="label">Our Store · Kichijoji</span>
          <h2 class="section-title">吉祥寺の実店舗</h2>
        </div>
        <p class="section-sub">落ち着いた空間で、ゆっくりご相談いただけます</p>
      </div>
    </div>
    <div class="store-scroll reveal">
      <div class="store-scroll__item"><img src="images/interior-racks.jpg" alt="店内" loading="lazy" /></div>
      <div class="store-scroll__item"><img src="images/exterior.jpg" alt="外観" loading="lazy" /></div>
      <div class="store-scroll__item"><img src="images/sign-brands.jpg" alt="取扱ブランド" loading="lazy" /></div>
    </div>
  </section>

'''

# ── LINE過剰訴求を削減: フローのCTAエリアを整理 ──────────
# flow内の余分なLINEボタン数を減らす（flow-ctaは1つに）
FLOW = re.sub(
    r'(<div class="flow-cta">.*?</div>\s*){2,}',
    lambda m: m.group(0).split('</div>')[0] + '</div>\n        ',
    FLOW, flags=re.DOTALL
)

# ── デザイン変更: ダーク→清潔感ある白・ライトグレー ──────
# ヒーロー: オーバーレイを軽くする
HEAD = HEAD.replace(
    'background: linear-gradient(to right, rgba(0,0,0,0.72) 0%, rgba(0,0,0,0.45) 55%, rgba(0,0,0,0.2) 100%);',
    'background: linear-gradient(to bottom, rgba(0,0,0,0.45) 0%, rgba(0,0,0,0.55) 60%, rgba(0,0,0,0.35) 100%);'
)
# ヒーロー高さ縮小
HEAD = HEAD.replace('min-height: 65svh;', 'min-height: 60svh;')
HEAD = HEAD.replace('min-height: 88svh;', 'min-height: 60svh;')

# ダーク背景を明るいグレーに変更
HEAD = HEAD.replace('.brands { background: #3C3C3C;', '.brands { background: #F0EDE9;')
HEAD = HEAD.replace('.pain-answer { background: #3C3C3C;', '.pain-answer { background: #F0EDE9;')
HEAD = HEAD.replace('.footer { background: #3C3C3C;', '.footer { background: #222;')

# brands テキスト色を修正（白→黒系）
HEAD = HEAD.replace(
    '.brands .label { color: rgba(255,255,255,0.5); }',
    '.brands .label { color: var(--muted); }'
)
HEAD = HEAD.replace(
    '.brand-item { color: rgba(255,255,255,0.85);',
    '.brand-item { color: var(--ink);'
)
HEAD = HEAD.replace(
    '.brand-item:hover { color: #fff; }',
    '.brand-item:hover { color: var(--accent); }'
)
HEAD = HEAD.replace(
    '.section-title { color: #fff; }',
    '.section-title { color: var(--ink); }'
)

# ブランドセクションの見出しカラーを修正
BRANDS = BRANDS.replace(
    '<span class="label">', '<span class="label" style="color:var(--muted);">', 1
).replace(
    '<h2 class="section-title">', '<h2 class="section-title" style="color:var(--ink);">', 1
)

# ── 追加CSS ────────────────────────────────────────────────
EXTRA_CSS = '''
    /* === STORE SCROLL === */
    .store-scroll {
      display: flex; overflow-x: auto;
      scroll-snap-type: x mandatory;
      -webkit-overflow-scrolling: touch;
      margin-top: var(--sp-md);
    }
    .store-scroll::-webkit-scrollbar { height: 4px; }
    .store-scroll::-webkit-scrollbar-track { background: var(--rule); }
    .store-scroll::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 2px; }
    .store-scroll__item {
      min-width: min(88vw, 640px); scroll-snap-align: start;
      flex-shrink: 0; aspect-ratio: 4/3; overflow: hidden;
    }
    .store-scroll__item img { width:100%; height:100%; object-fit:cover; display:block; }

    /* === CASES SCROLL === */
    .cases-scroll {
      display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 1rem;
      scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;
    }
    .cases-scroll::-webkit-scrollbar { height: 4px; }
    .cases-scroll::-webkit-scrollbar-track { background: var(--rule); border-radius: 2px; }
    .cases-scroll::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 2px; }
    .cases-scroll .case { min-width: 260px; scroll-snap-align: start; flex-shrink: 0; }

    /* === OMOI === */
    .omoi { padding: var(--sp-xl) 0; background: #fff; }
    .omoi-body { margin-top: var(--sp-md); display:flex; flex-direction:column; gap:1.5rem; }
    .omoi-body p { font-size:1rem; line-height:2.1; color:#444; text-align:justify; }

'''
HEAD = HEAD.replace('  </style>\n</head>', EXTRA_CSS + '  </style>\n</head>')

HR = '\n  <hr class="divider" />\n\n\n'

# ── 組み立て ──────────────────────────────────────────────
parts = [
    HEAD, NAV,
    HERO, TAGROW, HR,
    ABOUT, HR,
    CASES, HR,
    PAIN, HR,
    FLOW,
    STORE,
    STYLISTS,
    OMOI,
    INSTAGRAM, HR,
    VOICES, HR,
    BRANDS, HR,
    FAQ,
    MAP,
    CTA,
    FOOTER,
]

with open('kaitori.html', 'w', encoding='utf-8') as f:
    f.write(''.join(parts))

print("完了")
