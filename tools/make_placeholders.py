"""Генерирует SVG-заглушки букетов в uploads/ и hero.svg. Запуск: python tools/make_placeholders.py"""
import math
import random
from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / "uploads"
OUT.mkdir(exist_ok=True)

GREEN = ["#8FA58B", "#7C947A", "#A7B9A0", "#6F8A6E"]

# id, палитра цветов, фон (верх, низ), тип, число голов
ITEMS = [
    ("b01", ["#F4C6CF", "#EBA9B7", "#FBE3E7", "#F7D5DB"], ("#F8E9EA", "#EFD3D8"), "bouquet", 11),
    ("b02", ["#F2A9BC", "#F7C6D3", "#E88BA4", "#FBE0E7"], ("#FBEFF1", "#F1D2DA"), "bouquet", 9),
    ("b03", ["#C94B6E", "#D9708D", "#B73A5E", "#E7A2B5"], ("#F5E4E7", "#E6BFC9"), "bouquet", 12),
    ("b04", ["#F6B9C8", "#F9D2DB", "#EE98AE", "#FFFFFF"], ("#F3EEE9", "#E4DCD5"), "tulips", 15),
    ("b05", ["#E9D9C8", "#D8BFA6", "#F3E8DC", "#C9A98C"], ("#F1EAE3", "#DDD0C5"), "bouquet", 11),
    ("b06", ["#7B2342", "#8F2E50", "#6A1B38", "#A34262"], ("#EDE2E3", "#D5BFC4"), "bouquet", 13),
    ("b07", ["#F7DDE3", "#E9C5E8", "#D9D2F1", "#F4B8C6"], ("#F6F1F4", "#E4D9E6"), "bouquet", 14),
    ("b08", ["#F8D3A9", "#F5B79A", "#FBE6C8", "#F2A58E"], ("#F8EFE6", "#EAD6C5"), "bouquet", 10),
    ("b09", ["#F4B6C6", "#F8D0DA", "#EE9CB1", "#FCE6EB"], ("#F7ECEE", "#EBD2D8"), "mini", 6),
    ("b10", ["#F3C3CE", "#EAA3B3", "#FBE2E7", "#CF6C86"], ("#F2E9E4", "#E1D1C8"), "box", 10),
    ("b11", ["#F6E27A", "#F4B6C6", "#B9C8F2", "#FFFFFF"], ("#F2EFE8", "#E0DACC"), "bouquet", 13),
    ("b12", ["#F9F4F6", "#E6B7CF", "#C9779E", "#F2DCE6"], ("#EDE8EA", "#D7CCD1"), "bouquet", 7),
    ("b13", ["#FFFFFF", "#F6EEE8", "#EADFD6", "#F9E7EA"], ("#EFEBE8", "#DAD2CC"), "bouquet", 12),
    ("b14", ["#EFA3B6", "#F4BBC8", "#E58AA2", "#F9D6DE"], ("#F9ECEF", "#EDCDD5"), "bouquet", 15),
    ("b15", ["#F5EFE6", "#EADFD0", "#DCC9B3", "#F9F5EF"], ("#F4EEE8", "#E3D8CD"), "bouquet", 10),
]


def flower(rnd, cx, cy, r, color):
    petals = rnd.choice([7, 8, 9])
    rot = rnd.uniform(0, 360)
    parts = []
    for i in range(petals):
        a = rot + i * 360 / petals
        parts.append(
            f'<ellipse cx="{cx:.1f}" cy="{cy - r * 0.52:.1f}" rx="{r * 0.46:.1f}" ry="{r * 0.58:.1f}" '
            f'fill="{color}" opacity="0.92" transform="rotate({a:.1f} {cx:.1f} {cy:.1f})"/>'
        )
    parts.append(f'<circle cx="{cx:.1f}" cy="{cy:.1f}" r="{r * 0.34:.1f}" fill="#fff" opacity="0.28"/>')
    parts.append(f'<circle cx="{cx:.1f}" cy="{cy:.1f}" r="{r * 0.14:.1f}" fill="#000" opacity="0.10"/>')
    return "".join(parts)


def leaves(rnd, cx, cy, spread, n):
    out = []
    for i in range(n):
        a = -90 + (i / (n - 1) - 0.5) * spread + rnd.uniform(-6, 6)
        ln = rnd.uniform(150, 215)
        col = rnd.choice(GREEN)
        out.append(
            f'<ellipse cx="{cx}" cy="{cy - ln * 0.5:.1f}" rx="{rnd.uniform(18, 28):.1f}" ry="{ln * 0.5:.1f}" '
            f'fill="{col}" opacity="0.9" transform="rotate({a + 90:.1f} {cx} {cy})"/>'
        )
    return "".join(out)


def wrap(w, h, bg, body, kind):
    base = (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" width="{w}" height="{h}">'
        f'<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1">'
        f'<stop offset="0" stop-color="{bg[0]}"/><stop offset="1" stop-color="{bg[1]}"/></linearGradient>'
        f'<filter id="s" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="14"/></filter></defs>'
        f'<rect width="{w}" height="{h}" fill="url(#g)"/>'
    )
    return base + body + "</svg>"


def bouquet(rnd, pal, n, w=600, h=750, kind="bouquet"):
    cx, cy = w / 2, 330
    body = [f'<ellipse cx="{cx}" cy="{h - 40}" rx="170" ry="22" fill="#3B2723" opacity="0.14" filter="url(#s)"/>']
    body.append(leaves(rnd, cx, 470, 120, 9))
    pts = []
    tries = 0
    scale = 1.0 if kind != "mini" else 0.72
    while len(pts) < n and tries < 800:
        tries += 1
        a = rnd.uniform(0, math.tau)
        d = rnd.uniform(0, 165 * scale) ** 1 * (rnd.random() ** 0.35)
        x, y = cx + math.cos(a) * d, cy + math.sin(a) * d * 0.82
        r = rnd.uniform(46, 62) * scale
        if all(math.hypot(x - px, y - py) > (r + pr) * 0.62 for px, py, pr in pts):
            pts.append((x, y, r))
    for x, y, r in sorted(pts, key=lambda p: p[1]):
        body.append(flower(rnd, x, y, r, rnd.choice(pal)))
    # упаковка
    body.append(
        f'<polygon points="{cx - 150},{470} {cx + 150},{470} {cx + 62},{h - 60} {cx - 62},{h - 60}" '
        f'fill="#FBF6F1" opacity="0.96"/>'
        f'<polygon points="{cx - 150},{470} {cx},{560} {cx + 150},{470} {cx + 110},{455} {cx},{505} {cx - 110},{455}" '
        f'fill="#fff" opacity="0.7"/>'
        f'<rect x="{cx - 58}" y="{h - 175}" width="116" height="16" rx="8" fill="#7B2342"/>'
        f'<ellipse cx="{cx}" cy="{h - 167}" rx="26" ry="14" fill="#7B2342"/>'
    )
    return wrap(w, h, ITEM_BG, "".join(body), kind)


def tulips(rnd, pal, n, w=600, h=750):
    cx = w / 2
    body = [f'<ellipse cx="{cx}" cy="{h - 40}" rx="170" ry="22" fill="#3B2723" opacity="0.14" filter="url(#s)"/>']
    stems = []
    for i in range(n):
        t = i / (n - 1)
        top_x = cx + (t - 0.5) * 300 + rnd.uniform(-14, 14)
        top_y = 250 + abs(t - 0.5) * 140 + rnd.uniform(-20, 20)
        stems.append((top_x, top_y))
        body.append(
            f'<path d="M{cx + (t - 0.5) * 60:.1f} 560 Q{(cx + top_x) / 2:.1f} {top_y + 160:.1f} {top_x:.1f} {top_y + 30:.1f}" '
            f'stroke="{rnd.choice(GREEN)}" stroke-width="9" fill="none" stroke-linecap="round"/>'
        )
    for i, (x, y) in enumerate(sorted(stems, key=lambda s: s[1])):
        c = pal[i % len(pal)]
        body.append(
            f'<path d="M{x - 30:.1f} {y + 40:.1f} Q{x - 36:.1f} {y - 30:.1f} {x - 16:.1f} {y - 48:.1f} '
            f'L{x:.1f} {y - 22:.1f} L{x + 16:.1f} {y - 48:.1f} Q{x + 36:.1f} {y - 30:.1f} {x + 30:.1f} {y + 40:.1f} '
            f'Q{x:.1f} {y + 62:.1f} {x - 30:.1f} {y + 40:.1f}Z" fill="{c}"/>'
            f'<path d="M{x:.1f} {y - 22:.1f} Q{x - 10:.1f} {y + 20:.1f} {x:.1f} {y + 58:.1f} Q{x + 10:.1f} {y + 20:.1f} {x:.1f} {y - 22:.1f}Z" '
            f'fill="#fff" opacity="0.22"/>'
        )
    cy = 470
    body.append(
        f'<polygon points="{cx - 150},{cy} {cx + 150},{cy} {cx + 62},{h - 60} {cx - 62},{h - 60}" fill="#FBF6F1" opacity="0.96"/>'
        f'<rect x="{cx - 58}" y="{h - 175}" width="116" height="16" rx="8" fill="#7B2342"/>'
        f'<ellipse cx="{cx}" cy="{h - 167}" rx="26" ry="14" fill="#7B2342"/>'
    )
    return wrap(w, h, ITEM_BG, "".join(body), "tulips")


def box(rnd, pal, n, w=600, h=750):
    cx, cy = w / 2, 400
    body = [f'<ellipse cx="{cx}" cy="{h - 80}" rx="190" ry="24" fill="#3B2723" opacity="0.16" filter="url(#s)"/>']
    body.append(leaves(rnd, cx, 470, 150, 8))
    pts = []
    tries = 0
    while len(pts) < n and tries < 800:
        tries += 1
        a = rnd.uniform(math.pi, math.tau)
        d = 150 * (rnd.random() ** 0.5)
        x, y = cx + math.cos(a) * d * 1.1, 470 + math.sin(a) * d * 0.9
        r = rnd.uniform(46, 58)
        if all(math.hypot(x - px, y - py) > (r + pr) * 0.62 for px, py, pr in pts):
            pts.append((x, y, r))
    for x, y, r in sorted(pts, key=lambda p: p[1]):
        body.append(flower(rnd, x, y, r, rnd.choice(pal)))
    body.append(
        f'<rect x="{cx - 190}" y="480" width="380" height="190" rx="14" fill="#3B2723"/>'
        f'<rect x="{cx - 200}" y="462" width="400" height="40" rx="12" fill="#4A322E"/>'
        f'<rect x="{cx - 24}" y="480" width="48" height="190" fill="#7B2342" opacity="0.9"/>'
    )
    return wrap(w, h, ITEM_BG, "".join(body), "box")


def main():
    global ITEM_BG
    for i, (pid, pal, bg, kind, n) in enumerate(ITEMS):
        rnd = random.Random(i * 97 + 13)
        ITEM_BG = bg
        if kind == "tulips":
            svg = tulips(rnd, pal, n)
        elif kind == "box":
            svg = box(rnd, pal, n)
        else:
            svg = bouquet(rnd, pal, n, kind=kind)
        (OUT / f"{pid}.svg").write_text(svg, encoding="utf-8")

    # hero
    rnd = random.Random(7)
    ITEM_BG = ("#F6DCE2", "#EBB9C5")
    hero = bouquet(rnd, ["#F4C6CF", "#EBA9B7", "#FBE3E7", "#C94B6E", "#FFFFFF"], 16, w=800, h=900)
    (OUT.parent / "assets" / "hero.svg").write_text(hero, encoding="utf-8")
    print("готово:", len(ITEMS), "заглушек + hero.svg")


ITEM_BG = ("#F8E9EA", "#EFD3D8")

if __name__ == "__main__":
    (OUT.parent / "assets").mkdir(exist_ok=True)
    main()
