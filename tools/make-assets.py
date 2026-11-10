"""Builds the WordPress.org directory assets in .wordpress-org/ (banners and icons) from the GunsNPrices brand icon.

Usage: python3 tools/make-assets.py [path/to/gunsnprices-icon-512.png]
Dev-only file, not shipped in the plugin zip. Needs Pillow and the Arial fonts that come with macOS.
"""

import os
import sys

from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, ".wordpress-org")
ICON = sys.argv[1] if len(sys.argv) > 1 else os.path.join(os.path.dirname(ROOT), "brand", "gunsnprices-icon-512.png")
FONTS = "/System/Library/Fonts/Supplemental/"

ORANGE = (241, 90, 41)
BG_TOP = (28, 31, 36)
BG_BOTTOM = (40, 44, 51)
WHITE = (255, 255, 255)
GREY = (196, 201, 209)


def font(name, size):
    return ImageFont.truetype(os.path.join(FONTS, name), size)


def banner(width, height):
    """Draws the banner at 2x (1544x500) and scales it down when a smaller size is asked for."""
    w, h = 1544, 500
    im = Image.new("RGB", (w, h), BG_TOP)
    d = ImageDraw.Draw(im)
    for y in range(h):
        t = y / (h - 1)
        d.line([(0, y), (w, y)], fill=tuple(round(a + (b - a) * t) for a, b in zip(BG_TOP, BG_BOTTOM)))
    d.rectangle([0, h - 14, w, h], fill=ORANGE)

    icon = Image.open(ICON).convert("RGBA").resize((300, 300), Image.LANCZOS)
    im.paste(icon, (90, (h - 14 - 300) // 2), icon)

    x = 450
    d.text((x, 92), "GunsNPrices", font=font("Arial Bold.ttf", 46), fill=ORANGE)
    d.text((x, 146), "Firearm Specs", font=font("Arial Bold.ttf", 104), fill=WHITE)
    d.text((x, 284), "Caliber, model, magazine capacity, barrel length", font=font("Arial.ttf", 40), fill=GREY)
    d.text((x, 334), "and rounds per box from product titles.", font=font("Arial.ttf", 40), fill=GREY)
    d.text((x, 384), "Cost per round for ammo.", font=font("Arial Bold.ttf", 40), fill=WHITE)

    if (width, height) != (w, h):
        im = im.resize((width, height), Image.LANCZOS)
    return im


def main():
    os.makedirs(OUT, exist_ok=True)
    banner(1544, 500).save(os.path.join(OUT, "banner-1544x500.png"), optimize=True)
    banner(772, 250).save(os.path.join(OUT, "banner-772x250.png"), optimize=True)
    src = Image.open(ICON).convert("RGBA")
    for size in (128, 256):
        src.resize((size, size), Image.LANCZOS).save(os.path.join(OUT, f"icon-{size}x{size}.png"), optimize=True)
    for name in sorted(os.listdir(OUT)):
        print(name, os.path.getsize(os.path.join(OUT, name)), "bytes")


if __name__ == "__main__":
    main()
