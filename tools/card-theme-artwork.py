#!/usr/bin/env python3
"""
Generates the original "photo-style" artwork used by the premium portrait card
themes (App\\Support\\Themes\\PremiumThemes). Every image is drawn here from
scratch as SVG: an ambient, softly lit scene (gradient light, bokeh, surface)
with an industry still-life, plus a matching emblem used as the sample logo.

Output: public/images/card-themes/<key>/front.svg and emblem.svg
Run:    python3 tools/card-theme-artwork.py   (needs vendor/ for Lucide icons)

Canvas is 540 x 856 (10 px per mm of a 54 x 85.6 mm portrait card). The top
~45% is kept dark for the logo and name, the bottom ~18% sits under the
theme's icon band.
"""
import math
import os
import random
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LUCIDE = os.path.join(ROOT, 'vendor/mallardduck/blade-lucide-icons/resources/svg/icons')
OUT = os.path.join(ROOT, 'public/images/card-themes')
W, H = 540, 856


def lucide(name):
    with open(os.path.join(LUCIDE, name + '.svg')) as fh:
        svg = fh.read()
    return re.sub(r'^.*?<svg[^>]*>|</svg>\s*$', '', svg, flags=re.S).strip()


def defs(t):
    a1, a2 = t['gold']
    return f'''<defs>
<linearGradient id="bg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{t['bg'][0]}"/><stop offset="0.55" stop-color="{t['bg'][1]}"/><stop offset="1" stop-color="{t['bg'][2]}"/></linearGradient>
<radialGradient id="glow" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="{t['glow']}" stop-opacity="0.85"/><stop offset="1" stop-color="{t['glow']}" stop-opacity="0"/></radialGradient>
<radialGradient id="vig" cx="0.5" cy="0.45" r="0.75"><stop offset="0.55" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.75"/></radialGradient>
<linearGradient id="top" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#000" stop-opacity="0.72"/><stop offset="0.42" stop-color="#000" stop-opacity="0.45"/><stop offset="0.6" stop-color="#000" stop-opacity="0"/></linearGradient>
<linearGradient id="gold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{a1}"/><stop offset="1" stop-color="{a2}"/></linearGradient>
<filter id="b4" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="4"/></filter>
<filter id="b10" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="10"/></filter>
<filter id="b24" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="24"/></filter>
<filter id="b60" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="60"/></filter>
{t.get('defs', '')}
</defs>'''


def bokeh(t, seed, n=34, y0=40, y1=560):
    rnd = random.Random(seed)
    out = []
    for _ in range(n):
        x, y = rnd.uniform(-20, W + 20), rnd.uniform(y0, y1)
        r = rnd.uniform(6, 34)
        c = rnd.choice(t['bokeh'])
        op = rnd.uniform(0.12, 0.5)
        f = 'b4' if r < 14 else 'b10'
        out.append(f'<circle cx="{x:.0f}" cy="{y:.0f}" r="{r:.0f}" fill="{c}" opacity="{op:.2f}" filter="url(#{f})"/>')
    return ''.join(out)


def scene(t, hero, seed):
    lx, ly = t.get('light', (420, 150))
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">{defs(t)}
<rect width="{W}" height="{H}" fill="url(#bg)"/>
<ellipse cx="{lx}" cy="{ly}" rx="260" ry="220" fill="url(#glow)" opacity="0.55"/>
<g>{bokeh(t, seed)}</g>
{hero}
<rect width="{W}" height="{H}" fill="url(#top)"/>
<rect width="{W}" height="{H}" fill="url(#vig)"/>
</svg>'''


def table(top, c1, c2, grain='#000', grain_op=0.18, seed=1):
    rnd = random.Random(seed)
    lines = ''.join(
        f'<path d="M-10 {top + 14 + i * 16 + rnd.uniform(-4, 4):.0f} C 150 {top + 8 + i * 16 + rnd.uniform(-6, 6):.0f}, 380 {top + 22 + i * 16 + rnd.uniform(-6, 6):.0f}, 560 {top + 12 + i * 16:.0f}" stroke="{grain}" stroke-opacity="{grain_op}" stroke-width="{rnd.uniform(1, 3):.1f}" fill="none"/>'
        for i in range(16))
    return f'''<linearGradient id="tbl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{c1}"/><stop offset="1" stop-color="{c2}"/></linearGradient>''', \
        f'<rect x="0" y="{top}" width="{W}" height="{H - top}" fill="url(#tbl)"/>{lines}<rect x="0" y="{top}" width="{W}" height="3" fill="#fff" opacity="0.06"/>'


# ----------------------------------------------------------------------------- heroes

def hero_bakery(t):
    d, surface = table(612, '#4a2c19', '#140a05', seed=3)
    t['defs'] = d + '''
<linearGradient id="cup" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#0b0807"/><stop offset="0.35" stop-color="#3a2f2a"/><stop offset="0.6" stop-color="#16110f"/><stop offset="1" stop-color="#050404"/></linearGradient>
<radialGradient id="crema" cx="0.45" cy="0.4" r="0.6"><stop offset="0" stop-color="#e2b07a"/><stop offset="0.6" stop-color="#b5733c"/><stop offset="1" stop-color="#5a3014"/></radialGradient>
<linearGradient id="crust" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f6c06a"/><stop offset="0.5" stop-color="#d4843a"/><stop offset="1" stop-color="#8a4518"/></linearGradient>
<linearGradient id="muffin" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e4a865"/><stop offset="1" stop-color="#9a5826"/></linearGradient>
<linearGradient id="wrap" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#4a250f"/><stop offset="0.5" stop-color="#7a4220"/><stop offset="1" stop-color="#3a1c0a"/></linearGradient>'''
    seg = lambda cx, cy, rx, ry, rot: f'<g transform="rotate({rot} {cx} {cy})"><ellipse cx="{cx}" cy="{cy}" rx="{rx}" ry="{ry}" fill="url(#crust)" stroke="#6b3412" stroke-width="3"/><ellipse cx="{cx - rx * 0.15}" cy="{cy - ry * 0.4}" rx="{rx * 0.55}" ry="{ry * 0.28}" fill="#fff3d6" opacity="0.35"/></g>'
    croissant = seg(262, 588, 26, 17, -48) + seg(458, 588, 26, 17, 48) + seg(306, 566, 40, 28, -24) + seg(414, 566, 40, 28, 24) + seg(360, 552, 52, 38, 0)
    chips = ''.join(f'<ellipse cx="{x}" cy="{y}" rx="6" ry="4" fill="#2a160a"/>' for x, y in [(372, 612), (398, 598), (420, 618), (386, 628), (444, 606), (356, 628), (410, 640)])
    beans = ''.join(f'<g transform="rotate({r} {x} {y})"><ellipse cx="{x}" cy="{y}" rx="11" ry="7" fill="#2b170c"/><path d="M{x - 9} {y} Q {x} {y - 3} {x + 9} {y}" stroke="#0d0603" stroke-width="2" fill="none"/></g>'
                    for x, y, r in [(40, 690, 20), (70, 676, -30), (500, 684, 40), (476, 672, -10), (300, 692, 60), (520, 660, 15)])
    steam = ''.join(f'<path d="M{x} 510 C {x - 20} 470, {x + 20} 450, {x} 410 S {x - 14} 360, {x + 4} 330" stroke="#fff" stroke-width="7" fill="none" opacity="0.22" filter="url(#b4)"/>' for x in (150, 182, 212))
    return surface + '<g transform="translate(30 -34) scale(1.0)">' + croissant + '</g>' + f'''
<ellipse cx="180" cy="676" rx="150" ry="24" fill="#000" opacity="0.5" filter="url(#b10)"/>
<ellipse cx="180" cy="660" rx="140" ry="28" fill="#0d0a09"/><ellipse cx="180" cy="654" rx="128" ry="22" fill="#231b17"/><ellipse cx="180" cy="652" rx="70" ry="10" fill="#0b0807"/>
<path d="M286 560 C 340 556, 342 626, 276 618" stroke="#151110" stroke-width="16" fill="none"/>
<path d="M80 548 C 82 630, 118 656, 180 656 C 242 656, 278 630, 280 548 Z" fill="url(#cup)"/>
<ellipse cx="180" cy="548" rx="100" ry="22" fill="#1d1715"/><ellipse cx="180" cy="550" rx="90" ry="18" fill="url(#crema)"/>
<path d="M180 566 C 150 548, 156 528, 172 532 C 176 533, 179 537, 180 541 C 181 537, 184 533, 188 532 C 204 528, 210 548, 180 566 Z" fill="#fbecd6" opacity="0.92"/>
<path d="M100 560 C 104 610, 120 636, 150 646" stroke="#fff" stroke-width="5" fill="none" opacity="0.12"/>
{steam}<g transform="translate(56 0)">
<ellipse cx="400" cy="690" rx="80" ry="12" fill="#000" opacity="0.5" filter="url(#b10)"/>
<path d="M336 610 L 464 610 L 448 690 L 352 690 Z" fill="url(#wrap)"/>
{''.join(f'<path d="M{352 + i * 12} 612 L {358 + i * 10.4} 688" stroke="#2c1406" stroke-width="2" opacity="0.6"/>' for i in range(10))}
<path d="M326 616 C 322 566, 360 548, 400 548 C 440 548, 478 566, 474 616 Z" fill="url(#muffin)"/>
<path d="M346 584 C 360 566, 384 560, 404 562" stroke="#fff3d6" stroke-width="5" fill="none" opacity="0.3"/>
{chips}</g>{beans}'''


def hero_restaurant(t):
    d, surface = table(606, '#2a0d12', '#0a0305', grain='#ff9a7a', grain_op=0.04, seed=5)
    t['defs'] = d + '''
<radialGradient id="plate" cx="0.5" cy="0.4" r="0.6"><stop offset="0" stop-color="#ffffff"/><stop offset="0.8" stop-color="#d9d4cf"/><stop offset="1" stop-color="#9c958f"/></radialGradient>
<linearGradient id="wine" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#3a0610"/><stop offset="0.5" stop-color="#8f1428"/><stop offset="1" stop-color="#2a030a"/></linearGradient>
<linearGradient id="glass" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fff" stop-opacity="0.35"/><stop offset="0.3" stop-color="#fff" stop-opacity="0.05"/><stop offset="0.85" stop-color="#fff" stop-opacity="0.18"/><stop offset="1" stop-color="#fff" stop-opacity="0.04"/></linearGradient>
<radialGradient id="flame" cx="0.5" cy="0.65" r="0.5"><stop offset="0" stop-color="#fff7d0"/><stop offset="0.5" stop-color="#ffb43c"/><stop offset="1" stop-color="#ff6a00" stop-opacity="0"/></radialGradient>
<radialGradient id="steak" cx="0.4" cy="0.35" r="0.7"><stop offset="0" stop-color="#9a4a2a"/><stop offset="1" stop-color="#3d1608"/></radialGradient>'''
    candle = lambda x, h: f'''<ellipse cx="{x}" cy="{560 - h}" rx="60" ry="60" fill="url(#glow)" opacity="0.6"/>
<rect x="{x - 14}" y="{580 - h}" width="28" height="{h + 30}" rx="4" fill="#efe6d4"/><rect x="{x - 14}" y="{580 - h}" width="9" height="{h + 30}" fill="#fff" opacity="0.4"/>
<path d="M{x} {552 - h} C {x - 9} {566 - h}, {x - 6} {578 - h}, {x} {580 - h} C {x + 6} {578 - h}, {x + 9} {566 - h}, {x} {552 - h} Z" fill="url(#flame)"/>'''
    return surface + candle(470, 70) + candle(430, 30) + f'''
<g><path d="M92 430 C 88 500, 104 540, 140 548 L 140 640 L 108 650 L 176 650 L 144 640 L 144 548 C 180 540, 196 500, 192 430 Z" fill="url(#glass)" stroke="#fff" stroke-opacity="0.25" stroke-width="2"/>
<path d="M96 482 C 100 520, 112 540, 142 544 C 172 540, 184 520, 188 482 Z" fill="url(#wine)"/><ellipse cx="142" cy="482" rx="46" ry="7" fill="#b3203a"/>
<path d="M104 444 C 102 480, 106 506, 118 526" stroke="#fff" stroke-width="5" fill="none" opacity="0.35"/></g>
<ellipse cx="300" cy="676" rx="170" ry="26" fill="#000" opacity="0.6" filter="url(#b10)"/>
<ellipse cx="300" cy="652" rx="160" ry="38" fill="url(#plate)"/><ellipse cx="300" cy="648" rx="112" ry="25" fill="#f4f0ea"/><ellipse cx="300" cy="648" rx="112" ry="25" fill="none" stroke="#c9bfb3" stroke-width="2"/>
<path d="M232 652 C 228 620, 270 606, 306 608 C 350 610, 372 628, 364 648 C 356 664, 250 668, 232 652 Z" fill="url(#steak)"/>
<path d="M250 628 L 340 618 M 246 640 L 352 630 M 250 652 L 356 642" stroke="#1f0a03" stroke-width="5" opacity="0.6"/>
<path d="M232 664 C 270 672, 330 672, 380 660" stroke="#7a1022" stroke-width="7" fill="none" stroke-linecap="round"/>
{''.join(f'<circle cx="{x}" cy="{y}" r="7" fill="{c}"/>' for x, y, c in [(356, 604, '#4c8a32'), (368, 596, '#5fa43c'), (344, 598, '#3b7326'), (272, 604, '#e04b2e')])}
<g fill="#cfc6bd"><rect x="476" y="600" width="7" height="80" rx="3"/><rect x="496" y="600" width="9" height="80" rx="4"/></g>'''


def hero_spa(t):
    d, surface = table(632, '#3a2a30', '#120a0e', grain='#ffd9e4', grain_op=0.04, seed=7)
    t['defs'] = d + '''
<radialGradient id="stone" cx="0.4" cy="0.3" r="0.8"><stop offset="0" stop-color="#7d7377"/><stop offset="1" stop-color="#1e1a1c"/></radialGradient>
<radialGradient id="petal" cx="0.5" cy="0.9" r="0.9"><stop offset="0" stop-color="#ffd6e2"/><stop offset="0.6" stop-color="#f29bb6"/><stop offset="1" stop-color="#c85d82"/></radialGradient>
<radialGradient id="flame" cx="0.5" cy="0.65" r="0.5"><stop offset="0" stop-color="#fff7d0"/><stop offset="0.5" stop-color="#ffb43c"/><stop offset="1" stop-color="#ff6a00" stop-opacity="0"/></radialGradient>
<linearGradient id="towel" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fbf3ee"/><stop offset="1" stop-color="#cdbfb8"/></linearGradient>'''
    stones = ''.join(f'<ellipse cx="{x}" cy="{y}" rx="{rx}" ry="{ry}" fill="url(#stone)"/><ellipse cx="{x - rx * 0.25}" cy="{y - ry * 0.4}" rx="{rx * 0.4}" ry="{ry * 0.2}" fill="#fff" opacity="0.18"/>'
                     for x, y, rx, ry in [(190, 650, 110, 34), (196, 604, 86, 28), (188, 566, 64, 22), (194, 534, 44, 16)])
    petals = ''.join(f'<path d="M370 560 C {370 + 46 * math.cos(math.radians(a - 20)):.0f} {560 + 46 * math.sin(math.radians(a - 20)):.0f}, {370 + 70 * math.cos(math.radians(a)):.0f} {560 + 70 * math.sin(math.radians(a)):.0f}, {370 + 72 * math.cos(math.radians(a)):.0f} {560 + 72 * math.sin(math.radians(a)):.0f} C {370 + 70 * math.cos(math.radians(a)):.0f} {560 + 70 * math.sin(math.radians(a)):.0f}, {370 + 46 * math.cos(math.radians(a + 20)):.0f} {560 + 46 * math.sin(math.radians(a + 20)):.0f}, 370 560 Z" fill="url(#petal)" stroke="#b44e74" stroke-width="1.5"/>'
                     for a in range(-90, 270, 72))
    leaves = ''.join(f'<path d="M{x} {y} C {x + dx * 0.4} {y - 40}, {x + dx} {y - 50}, {x + dx * 1.2} {y - 20} C {x + dx * 0.8} {y - 4}, {x + dx * 0.3} {y + 4}, {x} {y} Z" fill="#3e7a4f" opacity="0.9"/>'
                     for x, y, dx in [(310, 640, -90), (430, 640, 90), (360, 650, 60)])
    return surface + leaves + stones + f'''
<ellipse cx="420" cy="490" rx="70" ry="70" fill="url(#glow)" opacity="0.5"/>
<rect x="400" y="510" width="44" height="80" rx="6" fill="#f2e7df"/><rect x="400" y="510" width="14" height="80" fill="#fff" opacity="0.5"/>
<path d="M422 470 C 410 488, 414 504, 422 508 C 430 504, 434 488, 422 470 Z" fill="url(#flame)"/>
{petals}<circle cx="370" cy="560" r="12" fill="#ffe08a"/>
<g transform="translate(360 600)"><rect width="150" height="44" rx="22" fill="url(#towel)"/><ellipse cx="150" cy="22" rx="14" ry="22" fill="#e8dcd4"/><path d="M146 6 C 156 14, 156 30, 146 38" stroke="#b9aaa1" stroke-width="2" fill="none"/></g>'''


def hero_fitness(t):
    t['defs'] = '''
<linearGradient id="floor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1e2120"/><stop offset="1" stop-color="#050606"/></linearGradient>
<linearGradient id="steel" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e8ecef"/><stop offset="0.5" stop-color="#7d8790"/><stop offset="1" stop-color="#2b3036"/></linearGradient>
<linearGradient id="plate" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#0c0d0e"/><stop offset="0.45" stop-color="#3a3f44"/><stop offset="1" stop-color="#0a0b0c"/></linearGradient>
<radialGradient id="bell" cx="0.35" cy="0.3" r="0.8"><stop offset="0" stop-color="#4b5157"/><stop offset="1" stop-color="#0b0c0d"/></radialGradient>'''
    streaks = ''.join(f'<path d="M{-40 + i * 70} {700 - i * 18} L {260 + i * 70} {300 - i * 30}" stroke="{t["gold"][0]}" stroke-width="{3 + i % 3 * 2}" opacity="{0.08 + (i % 4) * 0.05:.2f}" filter="url(#b4)"/>' for i in range(8))
    plate = lambda x: f'<rect x="{x}" y="548" width="34" height="120" rx="8" fill="url(#plate)" stroke="{t["gold"][0]}" stroke-opacity="0.5" stroke-width="2"/><rect x="{x + 6}" y="556" width="6" height="104" rx="3" fill="#fff" opacity="0.12"/>'
    return f'''<rect x="0" y="626" width="{W}" height="{H - 626}" fill="url(#floor)"/>{streaks}
<ellipse cx="200" cy="690" rx="200" ry="20" fill="#000" opacity="0.7" filter="url(#b10)"/>
<rect x="40" y="598" width="320" height="20" rx="8" fill="url(#steel)"/>
{plate(66)}{plate(104)}{plate(262)}{plate(300)}
<rect x="40" y="590" width="22" height="36" rx="5" fill="url(#steel)"/><rect x="338" y="590" width="22" height="36" rx="5" fill="url(#steel)"/>
<ellipse cx="440" cy="688" rx="80" ry="14" fill="#000" opacity="0.7" filter="url(#b10)"/>
<path d="M398 560 C 398 500, 482 500, 482 560" stroke="url(#bell)" stroke-width="22" fill="none"/>
<circle cx="440" cy="620" r="70" fill="url(#bell)"/><ellipse cx="416" cy="590" rx="26" ry="14" fill="#fff" opacity="0.12"/>
<rect x="410" y="612" width="60" height="18" rx="4" fill="{t['gold'][0]}"/>
<text x="440" y="626" font-family="Arial Black, Arial" font-weight="900" font-size="15" text-anchor="middle" fill="#0b0c0d">24KG</text>'''


def hero_realestate(t):
    rnd = random.Random(11)
    windows = ''
    for bx, by, cols, rows in [(110, 520, 6, 3), (300, 470, 4, 5)]:
        for c in range(cols):
            for r in range(rows):
                lit = rnd.random() > 0.25
                windows += f'<rect x="{bx + c * 26}" y="{by + r * 34}" width="18" height="24" fill="{"#ffcf7a" if lit else "#1c2a40"}" opacity="{0.95 if lit else 0.8}"/>'
    palm = lambda x, s: f'<g transform="translate({x} 640) scale({s})" fill="#05080f"><path d="M-3 0 C -6 -60, 0 -120, 8 -170 L 12 -168 C 6 -120, 4 -60, 5 0 Z"/>' + ''.join(
        f'<path d="M10 -170 C {10 + 60 * math.cos(math.radians(a))} {-170 + 40 * math.sin(math.radians(a)) - 20}, {10 + 90 * math.cos(math.radians(a))} {-170 + 60 * math.sin(math.radians(a))}, {10 + 95 * math.cos(math.radians(a))} {-160 + 70 * math.sin(math.radians(a))} C {10 + 60 * math.cos(math.radians(a))} {-170 + 30 * math.sin(math.radians(a))}, 20 -168, 10 -170 Z"/>'
        for a in (-170, -140, -100, -60, -20, 10, 200)) + '</g>'
    t['defs'] = '''<linearGradient id="pool" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2fa7c9"/><stop offset="1" stop-color="#0b3550"/></linearGradient>
<linearGradient id="wall" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#e9e4dc"/><stop offset="1" stop-color="#9c968d"/></linearGradient>'''
    return f'''<ellipse cx="270" cy="600" rx="420" ry="120" fill="#f79b4c" opacity="0.35" filter="url(#b60)"/>
<rect x="0" y="640" width="{W}" height="{H - 640}" fill="#06090f"/>
<rect x="96" y="500" width="180" height="128" fill="#101a2b"/><rect x="88" y="492" width="200" height="12" fill="url(#wall)"/>
<rect x="286" y="450" width="130" height="178" fill="#0e1828"/><rect x="278" y="440" width="150" height="14" fill="url(#wall)"/>
<rect x="60" y="610" width="400" height="22" fill="url(#wall)"/>
{windows}
<rect x="40" y="634" width="460" height="40" fill="url(#pool)"/><rect x="40" y="634" width="460" height="40" fill="#ffcf7a" opacity="0.12"/>
{''.join(f'<rect x="{80 + i * 50}" y="{646 + (i % 2) * 8}" width="{20 + i % 3 * 8}" height="2" fill="#ffe2a8" opacity="0.6"/>' for i in range(8))}
{palm(30, 1.1)}{palm(500, 0.9)}'''


def hero_consulting(t):
    rnd = random.Random(13)
    towers = ''
    x = -10
    while x < W:
        w = rnd.randint(40, 80)
        h = rnd.randint(140, 330)
        y = 690 - h
        towers += f'<rect x="{x}" y="{y}" width="{w}" height="{h + 10}" fill="url(#tower)"/>'
        for wy in range(y + 12, 680, 16):
            for wx in range(x + 6, x + w - 8, 10):
                if rnd.random() > 0.55:
                    towers += f'<rect x="{wx}" y="{wy}" width="5" height="7" fill="#ffe3a3" opacity="{rnd.uniform(0.35, 0.9):.2f}"/>'
        x += w + rnd.randint(2, 10)
    t['defs'] = f'''<linearGradient id="tower" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#1b2533"/><stop offset="0.6" stop-color="#0d131c"/><stop offset="1" stop-color="#070a0f"/></linearGradient>
<linearGradient id="water" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0d1622"/><stop offset="1" stop-color="#03060a"/></linearGradient>'''
    return f'''<ellipse cx="270" cy="560" rx="380" ry="120" fill="{t['glow']}" opacity="0.3" filter="url(#b60)"/>{towers}
<rect x="0" y="688" width="{W}" height="{H - 688}" fill="url(#water)"/>
{''.join(f'<rect x="{rnd.randint(0, W)}" y="{rnd.randint(696, 760)}" width="{rnd.randint(10, 40)}" height="2" fill="#ffe3a3" opacity="{rnd.uniform(0.15, 0.5):.2f}"/>' for _ in range(40))}'''


def hero_photo(t):
    t['defs'] = '''<linearGradient id="body" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2d2f33"/><stop offset="1" stop-color="#0a0b0c"/></linearGradient>
<radialGradient id="lens" cx="0.4" cy="0.35" r="0.7"><stop offset="0" stop-color="#5b7fa8"/><stop offset="0.35" stop-color="#1a2840"/><stop offset="0.7" stop-color="#3a1f52"/><stop offset="1" stop-color="#050507"/></radialGradient>
<linearGradient id="ring" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6c7178"/><stop offset="0.5" stop-color="#16181b"/><stop offset="1" stop-color="#4a4e54"/></linearGradient>
<linearGradient id="surf" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1a1714"/><stop offset="1" stop-color="#050404"/></linearGradient>'''
    knurl = ''.join(f'<rect x="{-118 + i * 8}" y="-6" width="3" height="12" fill="#000" opacity="0.5" transform="rotate(0)"/>' for i in range(0))
    return f'''<rect x="0" y="640" width="{W}" height="{H - 640}" fill="url(#surf)"/>
<ellipse cx="270" cy="680" rx="230" ry="22" fill="#000" opacity="0.7" filter="url(#b10)"/>
<path d="M90 500 L 190 500 L 210 470 L 320 470 L 340 500 L 450 500 Q 470 500 470 520 L 470 650 Q 470 670 450 670 L 90 670 Q 70 670 70 650 L 70 520 Q 70 500 90 500 Z" fill="url(#body)"/>
<rect x="70" y="530" width="400" height="110" fill="#111214"/>{''.join(f'<rect x="72" y="{534 + i * 6}" width="396" height="2" fill="#000" opacity="0.6"/>' for i in range(18))}
<rect x="380" y="482" width="48" height="18" rx="4" fill="#2a2c30"/><circle cx="120" cy="490" r="14" fill="#3a3c40"/><rect x="226" y="482" width="78" height="14" rx="3" fill="#0b0c0d"/>
<circle cx="270" cy="586" r="104" fill="url(#ring)"/><circle cx="270" cy="586" r="90" fill="#0a0b0c"/>
{''.join(f'<rect x="268" y="484" width="4" height="10" fill="#000" opacity="0.55" transform="rotate({a} 270 586)"/>' for a in range(0, 360, 8))}
<circle cx="270" cy="586" r="78" fill="url(#ring)"/><circle cx="270" cy="586" r="66" fill="url(#lens)"/><circle cx="270" cy="586" r="30" fill="#020203"/>
<ellipse cx="246" cy="560" rx="22" ry="12" fill="#fff" opacity="0.35" transform="rotate(-30 246 560)"/><circle cx="296" cy="610" r="6" fill="#fff" opacity="0.25"/>
<circle cx="430" cy="420" r="80" fill="{t['gold'][0]}" opacity="0.18" filter="url(#b24)"/><circle cx="430" cy="420" r="8" fill="#fff" opacity="0.8" filter="url(#b4)"/>
<path d="M430 420 L 140 700" stroke="{t['gold'][0]}" stroke-width="2" opacity="0.25"/>
{''.join(f'<circle cx="{430 - k * 50}" cy="{420 + k * 48}" r="{14 - k * 2 if k < 6 else 6}" fill="none" stroke="{t["gold"][0]}" stroke-width="2" opacity="0.3"/>' for k in range(2, 7))}{knurl}'''


def hero_tech(t):
    rnd = random.Random(17)
    cx, cy, r = 270, 600, 150
    nodes = []
    for _ in range(46):
        a = rnd.uniform(0, 2 * math.pi)
        rr = r * math.sqrt(rnd.uniform(0.05, 1))
        nodes.append((cx + rr * math.cos(a), cy + rr * math.sin(a) * 0.95))
    links = ''
    for i, (x1, y1) in enumerate(nodes):
        for x2, y2 in nodes[i + 1:]:
            if math.hypot(x2 - x1, y2 - y1) < 62:
                links += f'<line x1="{x1:.0f}" y1="{y1:.0f}" x2="{x2:.0f}" y2="{y2:.0f}" stroke="{t["gold"][0]}" stroke-width="1.4" opacity="0.45"/>'
    dots = ''.join(f'<circle cx="{x:.0f}" cy="{y:.0f}" r="{rnd.uniform(2.5, 5):.1f}" fill="#d9fbff"/>' for x, y in nodes)
    meridians = ''.join(f'<ellipse cx="{cx}" cy="{cy}" rx="{r * abs(math.cos(math.radians(a))):.0f}" ry="{r}" fill="none" stroke="{t["gold"][0]}" stroke-width="1.2" opacity="0.25"/>' for a in (0, 30, 60))
    parallels = ''.join(f'<ellipse cx="{cx}" cy="{cy + r * math.sin(math.radians(a)):.0f}" rx="{r * math.cos(math.radians(a)):.0f}" ry="{r * 0.12 * math.cos(math.radians(a)):.0f}" fill="none" stroke="{t["gold"][0]}" stroke-width="1.2" opacity="0.25"/>' for a in (-50, -25, 0, 25, 50))
    circuits = ''.join(f'<path d="M{x} {H} L {x} {y} L {x + dx} {y - 40}" stroke="{t["gold"][0]}" stroke-width="2" fill="none" opacity="0.25"/><circle cx="{x + dx}" cy="{y - 40}" r="5" fill="none" stroke="{t["gold"][0]}" stroke-width="2" opacity="0.4"/>'
                       for x, y, dx in [(40, 720, 30), (80, 760, 40), (470, 730, -30), (510, 700, -20), (430, 780, -40)])
    t['defs'] = f'<radialGradient id="globe" cx="0.4" cy="0.35" r="0.7"><stop offset="0" stop-color="{t["gold"][0]}" stop-opacity="0.35"/><stop offset="1" stop-color="#020814" stop-opacity="0.9"/></radialGradient>'
    return f'''<circle cx="{cx}" cy="{cy}" r="{r + 50}" fill="{t['gold'][0]}" opacity="0.25" filter="url(#b60)"/>
<circle cx="{cx}" cy="{cy}" r="{r}" fill="url(#globe)" stroke="{t['gold'][0]}" stroke-width="2" stroke-opacity="0.6"/>
{meridians}{parallels}{links}{dots}{circuits}
<ellipse cx="{cx}" cy="{cy}" rx="{r + 40}" ry="34" fill="none" stroke="{t['gold'][0]}" stroke-width="2.5" opacity="0.5" transform="rotate(-14 {cx} {cy})"/>'''


# ----------------------------------------------------------------------------- emblem

def emblem(t):
    """Arc + Lucide icon + sprigs: a stand-in logo in the theme's colours."""
    a1, a2 = t['gold']
    icon = lucide(t['emblem'])
    leaf = lambda x, y, rot: f'<path transform="rotate({rot} {x} {y})" d="M{x} {y} c 6 -10, 16 -12, 22 -10 c -4 8, -14 14, -22 10 Z" fill="url(#eg)"/>'
    sprig = lambda sx, flip: f'<g transform="translate({sx} 0) scale({flip} 1)"><path d="M0 128 C 14 118, 28 104, 36 86" stroke="url(#eg)" stroke-width="3" fill="none"/>' + leaf(8, 120, -30) + leaf(18, 110, -50) + leaf(28, 98, -70) + '</g>'
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 150" width="200" height="150">
<defs><linearGradient id="eg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{a1}"/><stop offset="1" stop-color="{a2}"/></linearGradient></defs>
<path d="M28 132 A 74 74 0 1 1 172 132" stroke="url(#eg)" stroke-width="5" fill="none" stroke-linecap="round"/>
{sprig(36, 1)}{sprig(164, -1)}
<g transform="translate(52 22) scale(4)" fill="none" stroke="{t['cream']}" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">{icon}</g>
</svg>'''


THEMES = {
    'bakery-cafe': dict(bg=('#1b120c', '#2b1a10', '#0d0805'), glow='#ffb257', bokeh=['#ffcf8a', '#ff9e3d', '#fff1c9', '#7da35a'],
                        gold=('#f4c46a', '#c98a2b'), cream='#fff4e2', emblem='chef-hat', hero=hero_bakery),
    'fine-dining': dict(bg=('#14070a', '#220b10', '#070203'), glow='#ff8a4a', bokeh=['#ffb36b', '#ff6a4a', '#ffd9a0'],
                        gold=('#e9c27a', '#a8742c'), cream='#fbeee0', emblem='utensils-crossed', hero=hero_restaurant, light=(460, 380)),
    'salon-spa': dict(bg=('#1c1016', '#2b1822', '#0c070a'), glow='#ffc2d4', bokeh=['#ffd1de', '#f7a8c0', '#fff0d6', '#c9a2ff'],
                      gold=('#f3c9b4', '#c88c74'), cream='#fff3ef', emblem='flower-2', hero=hero_spa, light=(140, 160)),
    'fitness-studio': dict(bg=('#070808', '#101312', '#030404'), glow='#c8f53a', bokeh=['#c8f53a', '#8fe03a', '#ffffff'],
                           gold=('#d4ff3f', '#8cc21a'), cream='#ffffff', emblem='dumbbell', hero=hero_fitness, light=(470, 220)),
    'luxury-real-estate': dict(bg=('#0a1324', '#24264a', '#7a4a52'), glow='#ffb26b', bokeh=['#ffd9a0', '#ffffff', '#ffb26b'],
                               gold=('#ecd08f', '#b48a3c'), cream='#fff8ea', emblem='house', hero=hero_realestate, light=(270, 520)),
    'executive-consulting': dict(bg=('#05080d', '#0c1422', '#05080d'), glow='#6fa8ff', bokeh=['#9cc4ff', '#ffe3a3', '#ffffff'],
                                 gold=('#dfe7f2', '#8fa3bd'), cream='#ffffff', emblem='chart-no-axes-combined', hero=hero_consulting, light=(270, 560)),
    'photography-studio': dict(bg=('#0b0a09', '#16120e', '#050404'), glow='#ffc26a', bokeh=['#ffd28a', '#ff9f5a', '#ffffff', '#a7c7ff'],
                               gold=('#ffcf7a', '#d18f2e'), cream='#fff7ea', emblem='aperture', hero=hero_photo, light=(430, 420)),
    'tech-startup': dict(bg=('#030712', '#071633', '#02040b'), glow='#22d3ee', bokeh=['#22d3ee', '#818cf8', '#ffffff'],
                         gold=('#4ee6ff', '#2a8cff'), cream='#eafcff', emblem='cpu', hero=hero_tech, light=(270, 560)),
}

if __name__ == '__main__':
    for i, (key, t) in enumerate(THEMES.items()):
        hero = t['hero'](t)
        os.makedirs(os.path.join(OUT, key), exist_ok=True)
        with open(os.path.join(OUT, key, 'front.svg'), 'w') as fh:
            fh.write(scene(t, hero, seed=100 + i))
        with open(os.path.join(OUT, key, 'emblem.svg'), 'w') as fh:
            fh.write(emblem(t))
        print('wrote', key)
