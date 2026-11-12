#!/usr/bin/env python3
"""Build 1200x630 social share images from photos already in assets/.

Run from anywhere:
    python3 tools/generate-share-images.py

Inter (tools/fonts/) is used under the SIL Open Font License.
"""
from pathlib import Path

from PIL import Image, ImageDraw, ImageEnhance, ImageFilter, ImageFont

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / 'assets'
FONT_DIR = Path(__file__).resolve().parent / 'fonts'
WIDTH, HEIGHT = 1200, 630
NAVY = (20, 33, 61, 255)
GOLD = (252, 163, 17, 255)
WHITE = (255, 255, 255, 255)


def font(weight, size):
    name = 'Inter-Bold.ttf' if weight == 'bold' else 'Inter-Medium.ttf'
    local = FONT_DIR / name
    if local.is_file():
        return ImageFont.truetype(str(local), size)
    system = Path('/usr/share/fonts/truetype/macos') / name
    if system.is_file():
        return ImageFont.truetype(str(system), size)
    return ImageFont.load_default()


def cover(path, box, focus=(0.5, 0.42)):
    image = Image.open(path).convert('RGB')
    target_w, target_h = box
    scale = max(target_w / image.width, target_h / image.height)
    resized = image.resize((round(image.width * scale), round(image.height * scale)), Image.Resampling.LANCZOS)
    left = max(0, min(resized.width - target_w, round((resized.width - target_w) * focus[0])))
    top = max(0, min(resized.height - target_h, round((resized.height - target_h) * focus[1])))
    return resized.crop((left, top, left + target_w, top + target_h))


def logo_mark(size):
    mark = Image.open(ASSETS / 'arrow-logo.png').convert('RGBA')
    pixels = mark.load()
    for y in range(mark.height):
        for x in range(mark.width):
            red, green, blue, alpha = pixels[x, y]
            if max(red, green, blue) < 28:
                pixels[x, y] = (red, green, blue, 0)
    return mark.resize((size, size), Image.Resampling.LANCZOS)


def darken(base, amount=0.55):
    shaded = ImageEnhance.Brightness(base).enhance(1 - amount)
    overlay = Image.new('RGBA', base.size, (8, 12, 24, int(255 * 0.45)))
    shade = Image.new('RGBA', base.size, (0, 0, 0, 0))
    gradient = ImageDraw.Draw(shade)
    for y in range(base.height):
        alpha = int(180 * (y / base.height) ** 1.4)
        gradient.line([(0, y), (base.width, y)], fill=(6, 10, 20, alpha))
    framed = ImageEnhance.Brightness(shaded).enhance(0.92).convert('RGBA')
    return Image.alpha_composite(Image.alpha_composite(framed, overlay), shade).convert('RGB')


def center_text(draw, text, y, face, fill):
    box = draw.textbbox((0, 0), text, font=face)
    x = (WIDTH - (box[2] - box[0])) // 2
    draw.text((x, y), text, font=face, fill=fill)


def save_jpg(image, name):
    path = ASSETS / name
    quality = 82
    while quality >= 60:
        image.save(path, format='JPEG', quality=quality, optimize=True, progressive=True, subsampling=2)
        if path.stat().st_size <= 300 * 1024:
            break
        quality -= 4
    print(f'{name} {path.stat().st_size} bytes q={quality}')


def home_card():
    card = darken(cover(ASSETS / 'arrow-home.jpg', (WIDTH, HEIGHT), (0.5, 0.38)))
    draw = ImageDraw.Draw(card)
    mark = logo_mark(168)
    card.paste(mark, ((WIDTH - mark.width) // 2, 118), mark)
    center_text(draw, 'SUNDAYS 12:30 PM  ·  FRIDAYS 7:00 PM', 340, font('bold', 36), WHITE)
    center_text(draw, 'Domingos 12:30 PM  ·  Viernes 7:00 PM', 392, font('medium', 28), GOLD)
    return card


def watch_card():
    card = darken(cover(ASSETS / 'arrow-home.jpg', (WIDTH, HEIGHT), (0.5, 0.36)), 0.62)
    draw = ImageDraw.Draw(card)
    mark = logo_mark(132)
    card.paste(mark, ((WIDTH - mark.width) // 2, 108), mark)
    center_text(draw, 'WATCH LIVE  ·  EN VIVO', 292, font('bold', 48), WHITE)
    center_text(draw, 'Sundays 12:30 PM  ·  Fridays 7:00 PM', 360, font('medium', 28), GOLD)
    return card


def contact_card():
    card = darken(cover(ASSETS / 'arrow-home.jpg', (WIDTH, HEIGHT), (0.48, 0.55)), 0.6)
    draw = ImageDraw.Draw(card)
    mark = logo_mark(132)
    card.paste(mark, ((WIDTH - mark.width) // 2, 100), mark)
    center_text(draw, 'CONTACT  ·  CONTACTO', 286, font('bold', 48), WHITE)
    center_text(draw, '2931 Lucerne Dr, Janesville, WI', 356, font('medium', 30), GOLD)
    center_text(draw, '(608) 774-4837', 404, font('medium', 28), WHITE)
    return card


def staff_tile(path, size):
    image = Image.open(path).convert('RGB')
    side = min(image.width, image.height)
    left = (image.width - side) // 2
    top = max(0, (image.height - side) // 5)
    tile = image.crop((left, top, left + side, min(image.height, top + side)))
    if tile.height < side:
        tile = image.crop((left, 0, left + side, side))
    tile = tile.resize((size, size), Image.Resampling.LANCZOS)
    framed = Image.new('RGB', (size, size), (16, 23, 36))
    framed.paste(tile, (0, 0))
    return framed.filter(ImageFilter.UnsharpMask(radius=1.2, percent=80, threshold=2))


def staff_card():
    card = Image.new('RGB', (WIDTH, HEIGHT), (16, 23, 36))
    draw = ImageDraw.Draw(card)
    files = [ASSETS / f'staff-{number:02d}.jpg' for number in range(1, 11)]
    size, gap = 168, 16
    columns = 5
    grid_w = columns * size + (columns - 1) * gap
    grid_h = 2 * size + gap
    origin_x = (WIDTH - grid_w) // 2
    origin_y = 48
    for index, path in enumerate(files):
        column = index % columns
        row = index // columns
        tile = staff_tile(path, size)
        x = origin_x + column * (size + gap)
        y = origin_y + row * (size + gap)
        card.paste(tile, (x, y))
        draw.rectangle([x, y + size - 4, x + size, y + size], fill=GOLD[:3])
    center_text(draw, 'STAFF & LEADERS', origin_y + grid_h + 28, font('bold', 36), WHITE)
    center_text(draw, 'Nuestro equipo', origin_y + grid_h + 76, font('medium', 26), GOLD)
    return card


def main():
    cards = {
        'share-home.jpg': home_card(),
        'share-staff.jpg': staff_card(),
        'share-watch.jpg': watch_card(),
        'share-contact.jpg': contact_card(),
    }
    for name, image in cards.items():
        if image.size != (WIDTH, HEIGHT):
            raise SystemExit(f'{name} is {image.size}, expected {(WIDTH, HEIGHT)}')
        save_jpg(image, name)


if __name__ == '__main__':
    main()
