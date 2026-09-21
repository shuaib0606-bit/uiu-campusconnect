#!/usr/bin/env python3
"""
Turns the supplied UIU CampusConnect logo into the files the site serves.

The artwork arrives as a 2000x2000 square with the orange baked in and a
lot of air around the mark - fine for a profile picture, too loose for a
36-pixel tile in a header, where the mark would shrink to a smudge. So the
content is measured, a square is cut around it with a fixed margin, and
that is what gets scaled down.

    python3 tools/make_logo_assets.py path/to/source.png

Writes into public/img/. Re-runnable; overwrites what it made last time.
"""

import os
import sys

from PIL import Image, ImageChops

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(os.path.dirname(HERE), 'public', 'img')

BACKGROUND = (255, 109, 47)

# How much of the cropped tile the mark should occupy along its long edge.
# 0.78 leaves enough air that the rounded corners do not clip the artwork.
FILL = 0.78

SIZES = {
    'logo.png': 512,
    'logo-192.png': 192,
    'logo-64.png': 64,
    'apple-touch-icon.png': 180,
}


def content_box(image: Image.Image) -> tuple:
    """The smallest rectangle holding everything that is not background."""
    flat = Image.new('RGB', image.size, BACKGROUND)
    mask = ImageChops.difference(image, flat).convert('L')
    # A threshold rather than getbbox() alone: the JPEG-ish edges of the
    # gradient figures never quite reach the exact background value.
    mask = mask.point(lambda p: 255 if p > 12 else 0)
    box = mask.getbbox()

    if box is None:
        sys.exit('The source is a flat colour - nothing to crop to.')

    return box


def main():
    source = sys.argv[1] if len(sys.argv) > 1 else os.path.join(OUT, 'logo-source.png')

    if not os.path.isfile(source):
        sys.exit(f'No such file: {source}')

    image = Image.open(source).convert('RGB')
    left, top, right, bottom = content_box(image)

    side = int(max(right - left, bottom - top) / FILL)
    centre_x = (left + right) // 2
    centre_y = (top + bottom) // 2

    tile = Image.new('RGB', (side, side), BACKGROUND)
    tile.paste(image, (side // 2 - centre_x, side // 2 - centre_y))

    os.makedirs(OUT, exist_ok=True)

    for name, size in SIZES.items():
        tile.resize((size, size), Image.LANCZOS).save(
            os.path.join(OUT, name), optimize=True)
        print(f'  public/img/{name}  {size}x{size}')

    # The untouched square, kept for anywhere the full artwork belongs:
    # documentation covers, link previews, an app store listing one day.
    image.resize((1024, 1024), Image.LANCZOS).save(
        os.path.join(OUT, 'logo-full.png'), optimize=True)
    print('  public/img/logo-full.png  1024x1024')


if __name__ == '__main__':
    main()
