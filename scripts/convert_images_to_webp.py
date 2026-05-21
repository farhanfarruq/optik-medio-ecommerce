#!/usr/bin/env python3
"""
Phase 4 PERF-1: Convert PNG → WebP for blog hero images (medio-fe/public/).

Output disimpan SEBELAH file PNG (tidak menghapus original — keep PNG sebagai
fallback untuk browser lawas yang tidak support WebP).

Quality 80 — sweet spot antara size & visual quality untuk hero image.
"""

from pathlib import Path
from PIL import Image

PUBLIC_DIR = Path(
    '/home/farhan/Documents/VsCode Project/optik-medio-ecommerce/medio-fe/public'
)

# Images yang akan di-convert.
TARGETS = [
    'blog_feature_1_face_shape_1777451535680.png',
    'blog_feature_2_blueray_lens_1777451550672.png',
    'blog_feature_3_trends_2026_1777451566973.png',
]

def convert(src: Path) -> tuple[int, int]:
    """Returns (original_bytes, webp_bytes)."""
    dst = src.with_suffix('.webp')
    img = Image.open(src)
    # Convert to RGB if RGBA → WebP supports both, but quality=80 RGB is smaller.
    # Keep alpha channel kalau ada karena hero image biasanya butuh transparency.
    if img.mode in ('RGBA', 'LA') or (img.mode == 'P' and 'transparency' in img.info):
        img = img.convert('RGBA')
    else:
        img = img.convert('RGB')

    img.save(dst, 'WEBP', quality=80, method=6)
    return src.stat().st_size, dst.stat().st_size


def main() -> None:
    total_before = 0
    total_after = 0
    for name in TARGETS:
        src = PUBLIC_DIR / name
        if not src.exists():
            print(f'SKIP (not found): {name}')
            continue
        before, after = convert(src)
        total_before += before
        total_after += after
        ratio = (1 - after / before) * 100 if before else 0
        print(
            f'CONVERTED {name:50s} '
            f'{before/1024:7.1f} KB → {after/1024:7.1f} KB  ({ratio:.1f}% smaller)'
        )

    if total_before:
        ratio = (1 - total_after / total_before) * 100
        print(
            f'\nTOTAL: {total_before/1024:.1f} KB → {total_after/1024:.1f} KB  '
            f'({ratio:.1f}% reduction, saved {(total_before-total_after)/1024:.1f} KB)'
        )


if __name__ == '__main__':
    main()
