#!/usr/bin/env python3
"""Generate a native macOS AppIcon set from the iPhone icon.

The iPhone icon is a full-bleed square; macOS icons float in a rounded tile with
transparent margins. We inset to ~80% and round the corners (~22% radius), then
emit every size the asset catalog needs plus its Contents.json.
"""
import json
import os
from PIL import Image, ImageDraw

SRC = "Tododeloo/Assets.xcassets/AppIcon.appiconset/AppIcon.png"
OUT = "TododelooMac/Assets.xcassets/AppIcon.appiconset"

os.makedirs(OUT, exist_ok=True)

src = Image.open(SRC).convert("RGBA")
CANVAS, TILE = 1024, 824
RADIUS = 184  # ~22.3% of the tile — the macOS continuous-corner look

tile = src.resize((TILE, TILE), Image.LANCZOS)
mask = Image.new("L", (TILE, TILE), 0)
ImageDraw.Draw(mask).rounded_rectangle([0, 0, TILE - 1, TILE - 1], radius=RADIUS, fill=255)
tile.putalpha(mask)

base = Image.new("RGBA", (CANVAS, CANVAS), (0, 0, 0, 0))
offset = (CANVAS - TILE) // 2
base.paste(tile, (offset, offset), tile)

for px in (16, 32, 64, 128, 256, 512, 1024):
    base.resize((px, px), Image.LANCZOS).save(f"{OUT}/icon_{px}.png")

images = [
    ("16x16", "1x", 16), ("16x16", "2x", 32),
    ("32x32", "1x", 32), ("32x32", "2x", 64),
    ("128x128", "1x", 128), ("128x128", "2x", 256),
    ("256x256", "1x", 256), ("256x256", "2x", 512),
    ("512x512", "1x", 512), ("512x512", "2x", 1024),
]
contents = {
    "images": [
        {"size": size, "idiom": "mac", "filename": f"icon_{px}.png", "scale": scale}
        for size, scale, px in images
    ],
    "info": {"version": 1, "author": "xcode"},
}
with open(f"{OUT}/Contents.json", "w") as f:
    json.dump(contents, f, indent=2)

with open("TododelooMac/Assets.xcassets/Contents.json", "w") as f:
    json.dump({"info": {"version": 1, "author": "xcode"}}, f, indent=2)

print("Generated macOS AppIcon set in", OUT)
