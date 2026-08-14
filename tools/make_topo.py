#!/usr/bin/env python3
"""
=============================================================================
 make_topo.py — generate a contour-map SVG from public elevation data.
=============================================================================

 Produces the topographic artwork used as background texture on the site.
 You do not need to run this unless you want a map of somewhere else.

     python tools/make_topo.py

 WHERE THE DATA COMES FROM
 Elevation is read from the AWS "terrarium" terrain tiles, which repackage
 USGS 3DEP and SRTM data. That data is public domain and the tiles are open
 and need no API key, so the resulting SVG is ours to use freely. We are NOT
 screen-scraping anybody's rendered map: the contours are computed here from
 raw elevations, which is why they can be recoloured with CSS.

 The trail overlay comes from a CalTopo GPS track exported to route.json.

 REQUIREMENTS
     pip install numpy scipy matplotlib pillow
=============================================================================
"""

import io
import json
import math
import os
import urllib.request

import numpy as np
import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt
from PIL import Image
from scipy.ndimage import gaussian_filter

# --------------------------------------------------------------------------
# Settings
# --------------------------------------------------------------------------

ZOOM = 14                 # ~8 m per pixel at this latitude
PAD = 0.14                # fraction of the bounding box added as margin
SMOOTH = 3.5              # gaussian blur in pixels, stops contours looking jagged
MINOR_FT = 200            # contour interval
INDEX_EVERY = 5           # every Nth contour is drawn heavier (so 1000 ft)
SIMPLIFY_PX = 2.6         # drop points closer together than this
MIN_POINTS = 6            # bin contour fragments shorter than this

# These four settings trade detail against file size. The SVG is background
# texture on every page, so it has to stay small: aim for well under 100 KB.
# Halving SIMPLIFY_PX roughly doubles the file.

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
OUT_DIR = os.path.join(ROOT, "assets", "images")

# Mt Wilson, if there is no route file to derive a box from.
FALLBACK_BBOX = (-118.075, 34.190, -118.010, 34.235)


# --------------------------------------------------------------------------
# Web Mercator helpers. Terrain tiles are already in this projection, so the
# mosaic's pixel grid IS the map projection — no reprojection needed.
# --------------------------------------------------------------------------

def deg2px(lat, lon, z):
    """Longitude/latitude to global pixel coordinates at zoom z."""
    n = 256 * 2 ** z
    x = (lon + 180.0) / 360.0 * n
    y = (1.0 - math.asinh(math.tan(math.radians(lat))) / math.pi) / 2.0 * n
    return x, y


def fetch_tile(z, x, y, cache_dir):
    """One terrain tile, cached on disk so re-runs are instant."""
    os.makedirs(cache_dir, exist_ok=True)
    path = os.path.join(cache_dir, "%d_%d_%d.png" % (z, x, y))
    if os.path.exists(path):
        return Image.open(path).convert("RGB")

    url = "https://s3.amazonaws.com/elevation-tiles-prod/terrarium/%d/%d/%d.png" % (z, x, y)
    req = urllib.request.Request(url, headers={"User-Agent": "caltech-alpine-club-map/1.0"})
    raw = urllib.request.urlopen(req, timeout=60).read()
    with open(path, "wb") as fh:
        fh.write(raw)
    return Image.open(io.BytesIO(raw)).convert("RGB")


def elevation_grid(bbox, z, cache_dir):
    """
    Assemble the tiles covering bbox and decode them to metres.
    Returns (elevation array, origin pixel x, origin pixel y).
    """
    west, south, east, north = bbox
    x0, y0 = deg2px(north, west, z)     # north-west corner
    x1, y1 = deg2px(south, east, z)     # south-east corner

    tx0, ty0 = int(x0 // 256), int(y0 // 256)
    tx1, ty1 = int(x1 // 256), int(y1 // 256)

    cols, rows = tx1 - tx0 + 1, ty1 - ty0 + 1
    print("  fetching %d tiles (%dx%d) at zoom %d" % (cols * rows, cols, rows, z))

    mosaic = np.zeros((rows * 256, cols * 256, 3), dtype=np.float64)
    for j, ty in enumerate(range(ty0, ty1 + 1)):
        for i, tx in enumerate(range(tx0, tx1 + 1)):
            tile = np.array(fetch_tile(z, tx, ty, cache_dir), dtype=np.float64)
            mosaic[j * 256:(j + 1) * 256, i * 256:(i + 1) * 256] = tile

    # Terrarium encoding: height = R*256 + G + B/256 - 32768, in metres.
    elev = mosaic[:, :, 0] * 256 + mosaic[:, :, 1] + mosaic[:, :, 2] / 256.0 - 32768.0

    # Crop to the requested box.
    ox, oy = tx0 * 256, ty0 * 256
    left, top = int(round(x0 - ox)), int(round(y0 - oy))
    right, bottom = int(round(x1 - ox)), int(round(y1 - oy))
    return elev[top:bottom, left:right], ox + left, oy + top


# --------------------------------------------------------------------------
# Contours to SVG paths
# --------------------------------------------------------------------------

def contour_segments(grid, levels):
    """{level: [array of (x, y) points, ...]} using whichever matplotlib API exists."""
    fig = plt.figure()
    cs = plt.contour(grid, levels=levels)
    out = {}
    try:                                     # matplotlib < 3.10
        for level, segs in zip(cs.levels, cs.allsegs):
            out[level] = [s for s in segs if len(s) > 3]
    except AttributeError:                   # matplotlib >= 3.10
        for level, path in zip(cs.levels, cs.get_paths()):
            out[level] = [np.asarray(p) for p in path.to_polygons(closed_only=False) if len(p) > 3]
    plt.close(fig)
    return out


def simplify(points, tol):
    """Drop points that are closer together than tol. Crude but effective."""
    if len(points) < 3:
        return points
    kept = [points[0]]
    for p in points[1:-1]:
        if (p[0] - kept[-1][0]) ** 2 + (p[1] - kept[-1][1]) ** 2 >= tol * tol:
            kept.append(p)
    kept.append(points[-1])
    return np.asarray(kept)


def to_path(points, sx, sy):
    """
    A polyline as an SVG path. Coordinates are rounded to whole units, which
    costs nothing visually at this scale and cuts the file size by about a
    third compared with one decimal place.
    """
    d = []
    for i, (px, py) in enumerate(points):
        d.append(("M" if i == 0 else "L") + "%.0f %.0f" % (px * sx, py * sy))
    return "".join(d).replace(" -", "-")


# --------------------------------------------------------------------------

def main():
    cache_dir = os.path.join(HERE, ".tilecache")

    # --- work out the area, from the GPS track if we have one --------------
    route = None
    route_file = os.path.join(HERE, "route.json")
    if os.path.exists(route_file):
        route = json.load(open(route_file))
        lons = [p[0] for p in route]
        lats = [p[1] for p in route]
        dx, dy = max(lons) - min(lons), max(lats) - min(lats)
        bbox = (min(lons) - dx * PAD, min(lats) - dy * PAD,
                max(lons) + dx * PAD, max(lats) + dy * PAD)
        print("  route: %d points" % len(route))
    else:
        bbox = FALLBACK_BBOX
        print("  no route.json, using the default box")

    print("  bbox: %.5f %.5f %.5f %.5f" % bbox)

    grid, ox, oy = elevation_grid(bbox, ZOOM, cache_dir)
    h, w = grid.shape
    print("  grid: %dx%d px, elevation %.0f–%.0f m (%.0f–%.0f ft)"
          % (w, h, grid.min(), grid.max(), grid.min() / 0.3048, grid.max() / 0.3048))

    smooth = gaussian_filter(grid, SMOOTH)

    # --- contour levels on round numbers of feet ---------------------------
    step_m = MINOR_FT * 0.3048
    lo = math.floor(smooth.min() / step_m) * step_m
    hi = math.ceil(smooth.max() / step_m) * step_m
    levels = list(np.arange(lo, hi + step_m, step_m))
    print("  %d contours every %d ft" % (len(levels), MINOR_FT))

    segs = contour_segments(smooth, levels)

    # Scale the pixel grid into a tidy viewBox.
    VB_W = 1400.0
    sx = VB_W / w
    sy = sx                        # square pixels: mercator is conformal
    VB_H = h * sy

    minor, index = [], []
    for i, level in enumerate(levels):
        # Index contours are round multiples of INDEX_EVERY * MINOR_FT.
        feet = level / 0.3048
        is_index = abs(round(feet / (MINOR_FT * INDEX_EVERY))
                       - feet / (MINOR_FT * INDEX_EVERY)) < 0.02
        bucket = index if is_index else minor
        for seg in segs.get(level, []):
            pts = simplify(seg, SIMPLIFY_PX)
            if len(pts) < MIN_POINTS:
                continue            # stub fragments add bytes and no legibility
            bucket.append(to_path(pts, sx, sy))

    print("  %d minor paths, %d index paths" % (len(minor), len(index)))

    # --- the trail, in the same pixel frame --------------------------------
    route_d = ""
    if route:
        pts = []
        for lon, lat, *_ in route:
            px, py = deg2px(lat, lon, ZOOM)
            pts.append(((px - ox) * sx, (py - oy) * sy))
        route_d = "".join(("M" if i == 0 else "L") + "%.1f %.1f" % (p[0], p[1])
                          for i, p in enumerate(simplify(np.asarray(pts), 0.8)))

    # --- write the texture version (contours only, currentColor) -----------
    head = ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.0f %.0f" '
            'fill="none" aria-hidden="true">' % (VB_W, VB_H))
    body = (
        '<g stroke="currentColor" fill="none" stroke-linejoin="round" stroke-linecap="round">'
        '<g stroke-width="0.8" opacity="0.55"><path d="%s"/></g>'
        '<g stroke-width="1.6" opacity="0.95"><path d="%s"/></g>'
        '</g>' % ("".join(minor), "".join(index))
    )
    texture = head + body + "</svg>"
    write(os.path.join(OUT_DIR, "mt-wilson-topo.svg"), texture)

    # --- write the illustrated version (contours + trail) ------------------
    if route_d:
        illus = (
            head
            + '<g stroke="currentColor" fill="none" stroke-linejoin="round" stroke-linecap="round">'
              '<g stroke-width="0.8" opacity="0.35"><path d="%s"/></g>'
              '<g stroke-width="1.5" opacity="0.6"><path d="%s"/></g>'
              '</g>' % ("".join(minor), "".join(index))
            + '<path d="%s" fill="none" stroke="#c0522d" stroke-width="4" '
              'stroke-linejoin="round" stroke-linecap="round"/>' % route_d
            + "</svg>"
        )
        write(os.path.join(OUT_DIR, "mt-wilson-trail.svg"), illus)


def write(path, text):
    with open(path, "w", encoding="utf-8") as fh:
        fh.write(text)
    print("  wrote %s (%.0f KB)" % (os.path.relpath(path, ROOT), len(text) / 1024.0))


if __name__ == "__main__":
    main()
