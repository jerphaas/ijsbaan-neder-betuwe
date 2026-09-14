"""Create lightweight web delivery copies; keep all source images unchanged."""
import hashlib
from pathlib import Path
import re
from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / 'dist/assets'
SPECS = {
    'logo-ijsbaan': ('jpg', [480], None, 90),
    'schaatsplezier': ('jpg', [660, 1100], '100vw', 84),
    'samen-op-het-ijs': ('jpg', [640, 1280], '(max-width: 640px) 90vw, 50vw', 84),
    'winter-op-de-baan': ('jpg', [480, 768], '(max-width: 640px) 90vw, 50vw', 84),
    'schaatsmaatjes': ('png', [240], None, 88),
}


def optimize_partner(source, stem):
    """Keep the original logo and colour treatment; return a small hashed WebP."""
    from io import BytesIO
    target = ASSETS / 'partners'
    target.mkdir(exist_ok=True)
    with Image.open(source) as original:
        copy = ImageOps.exif_transpose(original).convert('RGBA')
        copy.thumbnail((480, 180), Image.Resampling.LANCZOS)
        encoded = BytesIO()
        copy.save(encoded, format='WEBP', quality=92, method=6)
        data = encoded.getvalue()
    name = f'{stem}-{hashlib.sha256(data).hexdigest()[:10]}.webp'
    (target / name).write_bytes(data)
    return 'assets/partners/' + name


def main():
    page = ROOT / 'dist/index.html'
    html = page.read_text(encoding='utf-8')
    original_total = delivery_total = 0
    for stem, (extension, widths, sizes, quality) in SPECS.items():
        source = ASSETS / f'{stem}.{extension}'
        variants = []
        with Image.open(source) as original:
            original = ImageOps.exif_transpose(original)
            for width in widths:
                copy = original.copy()
                copy.thumbnail((width, round(original.height * width / original.width)), Image.Resampling.LANCZOS)
                from io import BytesIO
                encoded = BytesIO()
                copy.save(encoded, format='WEBP', quality=quality, method=6)
                data = encoded.getvalue()
                name = f'{stem}-{copy.width}-{hashlib.sha256(data).hexdigest()[:10]}.webp'
                (ASSETS / name).write_bytes(data)
                variants.append((name, copy.width, len(data)))
        pattern = rf'<img\b[^>]*src="assets/{stem}(?:\.{extension}|-[^"]+\.webp)"[^>]*>'
        def replace(match):
            tag = re.sub(r'\s(?:srcset|sizes|decoding)="[^"]*"', '', match[0])
            tag = re.sub(r'src="[^"]+"', f'src="assets/{variants[-1][0]}"', tag)
            attributes = ' decoding="async"'
            if sizes:
                sources = ', '.join(f'assets/{name} {width}w' for name, width, _ in variants)
                attributes += f' srcset="{sources}" sizes="{sizes}"'
            return tag[:-1] + attributes + '>'
        html, count = re.subn(pattern, replace, html)
        if count != 1:
            raise RuntimeError(f'Expected one image for {stem}, found {count}.')
        original_total += source.stat().st_size
        delivery_total += variants[-1][2]
        print(f'{stem}: {source.stat().st_size:,} -> {variants[-1][2]:,} bytes')
    page.write_text(html, encoding='utf-8')
    print(f'Total full-size image delivery: {original_total:,} -> {delivery_total:,} bytes.')


if __name__ == '__main__':
    main()
