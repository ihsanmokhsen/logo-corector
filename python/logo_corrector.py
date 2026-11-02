#!/usr/bin/env python3
"""
Logo Corrector NTT - mesin pemrosesan gambar.

Prinsip: hanya piksel di dalam kotak logo yang boleh berubah. Semua piksel di
luar kotak disalin apa adanya dari gambar asli, lalu diverifikasi ulang setelah
file hasil ditulis ke disk.

Perintah:
  detect  <image> <logo>
  replace <image> <logo> <output> --box x,y,w,h [--removal auto|fill|inpaint|none]
          [--scale 0.9] [--align center|left|right]

Keluaran selalu berupa satu objek JSON di stdout.
"""

import argparse
import json
import sys

import cv2
import numpy as np
from PIL import Image

Image.MAX_IMAGE_PIXELS = 100_000_000


# ---------------------------------------------------------------------------
# Utilitas
# ---------------------------------------------------------------------------

def fail(message, code=1):
    print(json.dumps({"ok": False, "error": message}))
    sys.exit(code)


def load_image(path):
    """Buka gambar tanpa rotasi EXIF, ubah ke mode kerja RGB/RGBA tanpa mengubah nilai warna."""
    im = Image.open(path)
    im.load()
    info = {
        "icc_profile": im.info.get("icc_profile"),
        "dpi": im.info.get("dpi"),
        "exif": im.info.get("exif"),
    }
    warnings = []
    mode = im.mode
    if mode in ("RGB", "RGBA"):
        work = im
    elif mode == "P":
        has_alpha = "transparency" in im.info
        work = im.convert("RGBA" if has_alpha else "RGB")
    elif mode == "L":
        work = im.convert("RGB")
    elif mode in ("LA", "PA"):
        work = im.convert("RGBA")
    elif mode == "CMYK":
        work = im.convert("RGB")
        warnings.append("Gambar CMYK dikonversi ke RGB untuk diproses.")
    else:
        work = im.convert("RGBA")
        warnings.append(f"Mode gambar {mode} dikonversi ke RGBA untuk diproses.")
    return work, info, warnings


def load_logo(path):
    """Logo resmi: RGBA, margin transparan dipangkas (bentuk dan proporsi tetap)."""
    logo = Image.open(path).convert("RGBA")
    bbox = logo.getchannel("A").getbbox()
    if bbox:
        logo = logo.crop(bbox)
    return logo


def parse_box(text, width, height):
    try:
        x, y, w, h = [int(round(float(v))) for v in text.split(",")]
    except ValueError:
        fail("Format kotak tidak valid. Gunakan x,y,w,h.")
    x0, y0 = max(0, x), max(0, y)
    x1, y1 = min(width, x + w), min(height, y + h)
    if x1 - x0 < 4 or y1 - y0 < 4:
        fail("Area logo terlalu kecil atau di luar gambar.")
    return x0, y0, x1 - x0, y1 - y0


# ---------------------------------------------------------------------------
# Deteksi
# ---------------------------------------------------------------------------

def iou(a, b):
    ax0, ay0, aw, ah = a
    bx0, by0, bw, bh = b
    ix = max(0, min(ax0 + aw, bx0 + bw) - max(ax0, bx0))
    iy = max(0, min(ay0 + ah, by0 + bh) - max(ay0, by0))
    inter = ix * iy
    union = aw * ah + bw * bh - inter
    return inter / union if union else 0.0


def contains(a, b):
    """Bagian kotak terkecil yang tertutup kotak lain (1.0 = seluruhnya di dalam)."""
    ax0, ay0, aw, ah = a
    bx0, by0, bw, bh = b
    ix = max(0, min(ax0 + aw, bx0 + bw) - max(ax0, bx0))
    iy = max(0, min(ay0 + ah, by0 + bh) - max(ay0, by0))
    smaller = min(aw * ah, bw * bh)
    return ix * iy / smaller if smaller else 0.0


def color_signature(bgr, mask):
    """Histogram warna (hue x saturasi) untuk paruh atas dan bawah objek; lambang NTT merah di atas, kuning di bawah."""
    hsv = cv2.cvtColor(bgr, cv2.COLOR_BGR2HSV)
    half = bgr.shape[0] // 2
    sig = []
    for rows in (slice(0, half), slice(half, None)):
        hist = cv2.calcHist([hsv[rows]], [0, 1], mask[rows], [18, 4], [0, 180, 0, 256])
        cv2.normalize(hist, hist, 1, 0, cv2.NORM_L1)
        sig.append(hist)
    return sig


def signature_similarity(a, b):
    return sum(cv2.compareHist(x, y, cv2.HISTCMP_INTERSECT) for x, y in zip(a, b)) / len(a)


def template_candidates(bgr, logo):
    """Cari objek yang mirip lambang NTT (mis. versi lama/buram): template matching berwarna multi-skala,
    lalu dicek kemiripan susunan warnanya agar pola acak di foto tidak ikut terpilih."""
    H, W = bgr.shape[:2]
    lw, lh = logo.size
    flat = Image.new("RGB", logo.size, (255, 255, 255))
    flat.paste(logo, mask=logo.getchannel("A"))
    tmpl_full = cv2.cvtColor(np.array(flat), cv2.COLOR_RGB2BGR)
    mask_full = np.array(logo.getchannel("A"))
    reference = color_signature(tmpl_full, (mask_full > 127).astype(np.uint8) * 255)

    # Kerjakan pada versi diperkecil agar cepat.
    factor = min(1.0, 450.0 / max(W, H))
    small = cv2.resize(bgr, (int(W * factor), int(H * factor)), interpolation=cv2.INTER_AREA) if factor < 1 else bgr
    sh, sw = small.shape[:2]

    results = []
    min_side = min(sw, sh)
    for frac in np.geomspace(0.04, 0.4, 18):
        th = int(min_side * frac)
        tw = int(th * lw / lh)
        if th < 20 or tw < 20 or th >= sh or tw >= sw:
            continue
        tmpl = cv2.resize(tmpl_full, (tw, th), interpolation=cv2.INTER_AREA)
        mask = cv2.resize(mask_full, (tw, th), interpolation=cv2.INTER_AREA)
        mask = (mask > 127).astype(np.uint8) * 255
        res = cv2.matchTemplate(small, tmpl, cv2.TM_CCOEFF_NORMED, mask=mask)
        res = np.nan_to_num(res, nan=-1.0, posinf=-1.0, neginf=-1.0)
        # Ambil beberapa puncak per skala, bukan hanya satu.
        for _ in range(3):
            _, shape_score, _, (x, y) = cv2.minMaxLoc(res)
            if shape_score < 0.3:
                break
            res[max(0, y - th // 2):y + th // 2 + 1, max(0, x - tw // 2):x + tw // 2 + 1] = -1
            color_score = signature_similarity(reference, color_signature(small[y:y + th, x:x + tw], mask))
            combined = 0.5 * shape_score + 0.5 * color_score
            if combined < 0.25:
                continue
            results.append({
                "box": [int(x / factor), int(y / factor), int(tw / factor), int(th / factor)],
                # Kecocokan bentuk + warna yang kuat lebih dapat dipercaya daripada objek berbentuk logo biasa.
                "score": float(combined + (1.0 if combined >= 0.35 else 0.5)),
                "source": "Bentuk & warna mirip lambang NTT",
            })
    return results


def contour_candidates(bgr):
    """Cari objek kompak (berbentuk seperti logo/lambang) dan beri skor berdasarkan posisi khas logo."""
    H, W = bgr.shape[:2]
    factor = min(1.0, 1000.0 / max(W, H))
    small = cv2.resize(bgr, (int(W * factor), int(H * factor)), interpolation=cv2.INTER_AREA) if factor < 1 else bgr
    sh, sw = small.shape[:2]
    gray = cv2.cvtColor(small, cv2.COLOR_BGR2GRAY)

    edges = cv2.Canny(cv2.GaussianBlur(gray, (3, 3), 0), 50, 150)
    k = max(3, int(min(sw, sh) * 0.012)) | 1
    closed = cv2.morphologyEx(edges, cv2.MORPH_CLOSE, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (k, k)), iterations=2)
    contours, _ = cv2.findContours(closed, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    area_img = sw * sh
    out = []
    for c in contours:
        x, y, w, h = cv2.boundingRect(c)
        area = w * h
        if area < area_img * 0.0015 or area > area_img * 0.08:
            continue
        aspect = w / float(h)
        if aspect < 0.55 or aspect > 1.8:
            continue
        fill = cv2.contourArea(c) / float(area)
        if fill < 0.35:
            continue
        cx, cy = (x + w / 2) / sw, (y + h / 2) / sh
        # Logo biasanya berada di bagian atas atau bawah, sering di pojok.
        vertical = max(1 - cy / 0.3, 0) if cy < 0.3 else max((cy - 0.8) / 0.2, 0) * 0.7
        horizontal = max(abs(cx - 0.5) * 2, 0.35)
        squareness = 1 - min(abs(np.log(aspect)), 1)
        size = min(area / (area_img * 0.01), 1)
        score = 0.25 + 0.3 * vertical * horizontal + 0.2 * squareness + 0.15 * fill + 0.1 * size
        out.append({
            "box": [int(x / factor), int(y / factor), int(w / factor), int(h / factor)],
            "score": float(min(score, 0.95)),
            "source": "Objek berbentuk logo",
        })
    return out


def detect(image_path, logo_path):
    work, _, warnings = load_image(image_path)
    rgb = np.array(work.convert("RGB"))
    bgr = cv2.cvtColor(rgb, cv2.COLOR_RGB2BGR)
    gray = cv2.cvtColor(bgr, cv2.COLOR_BGR2GRAY)
    logo = load_logo(logo_path)

    candidates = template_candidates(bgr, logo) + contour_candidates(bgr)
    candidates.sort(key=lambda c: c["score"], reverse=True)

    # Buang kandidat yang tumpang-tindih atau hanya potongan dari kandidat yang lebih baik.
    picked = []
    for cand in candidates:
        if all(iou(cand["box"], p["box"]) < 0.3 and contains(cand["box"], p["box"]) < 0.6 for p in picked):
            picked.append(cand)
        if len(picked) >= 6:
            break

    # Beri sedikit ruang agar tepi logo lama ikut tercakup.
    H, W = gray.shape
    for p in picked:
        x, y, w, h = p["box"]
        m = max(2, int(max(w, h) * 0.04))
        x0, y0 = max(0, x - m), max(0, y - m)
        x1, y1 = min(W, x + w + m), min(H, y + h + m)
        p["box"] = [x0, y0, x1 - x0, y1 - y0]
        p["score"] = round(min(p["score"], 1.0), 3)

    return {"ok": True, "width": W, "height": H, "candidates": picked, "warnings": warnings}


# ---------------------------------------------------------------------------
# Penggantian
# ---------------------------------------------------------------------------

def ring_pixels(arr, x, y, w, h, thickness=3):
    """Ambil piksel tipis tepat di luar kotak (hanya dibaca, tidak diubah)."""
    H, W = arr.shape[:2]
    parts = []
    if y - thickness >= 0:
        parts.append(arr[y - thickness:y, x:x + w])
    if y + h + thickness <= H:
        parts.append(arr[y + h:y + h + thickness, x:x + w])
    if x - thickness >= 0:
        parts.append(arr[y:y + h, x - thickness:x])
    if x + w + thickness <= W:
        parts.append(arr[y:y + h, x + w:x + w + thickness])
    if not parts:
        # Kotak menutupi seluruh gambar: pakai tepi bagian dalam.
        parts = [arr[y:y + thickness, x:x + w], arr[y + h - thickness:y + h, x:x + w]]
    return np.concatenate([p.reshape(-1, arr.shape[2]) for p in parts], axis=0)


def remove_logo(arr, x, y, w, h, method):
    """Kembalikan isi baru untuk kotak (h, w, C). Hanya membaca piksel di luar kotak."""
    region = arr[y:y + h, x:x + w].copy()
    ring = ring_pixels(arr, x, y, w, h)
    spread = float(ring[:, :3].astype(np.float32).std(axis=0).mean())

    if method == "auto":
        method = "fill" if spread < 14 else "inpaint"

    if method == "none":
        return region, "none"

    if method == "fill":
        color = np.median(ring, axis=0).astype(arr.dtype)
        region[:, :] = color
        return region, "fill"

    # Inpainting lokal: konteks di sekitar kotak dipakai sebagai acuan, hasil hanya diambil di dalam kotak.
    H, W = arr.shape[:2]
    margin = max(12, int(max(w, h) * 0.25))
    cx0, cy0 = max(0, x - margin), max(0, y - margin)
    cx1, cy1 = min(W, x + w + margin), min(H, y + h + margin)
    ctx = arr[cy0:cy1, cx0:cx1]
    mask = np.zeros(ctx.shape[:2], np.uint8)
    mask[y - cy0:y - cy0 + h, x - cx0:x - cx0 + w] = 255

    bgr = cv2.cvtColor(np.ascontiguousarray(ctx[:, :, :3]), cv2.COLOR_RGB2BGR)
    radius = max(3, int(min(w, h) * 0.05))
    filled = cv2.inpaint(bgr, mask, radius, cv2.INPAINT_TELEA)
    filled = cv2.cvtColor(filled, cv2.COLOR_BGR2RGB)
    region[:, :, :3] = filled[y - cy0:y - cy0 + h, x - cx0:x - cx0 + w]
    if arr.shape[2] == 4:
        region[:, :, 3] = np.median(ring[:, 3]).astype(arr.dtype)
    return region, "inpaint"


def place_logo(region, logo, scale, align):
    """Tempel logo resmi (ukuran disesuaikan, proporsi dijaga) ke dalam kotak dengan alpha compositing."""
    h, w = region.shape[:2]
    lw, lh = logo.size
    fit = min(w * scale / lw, h * scale / lh)
    tw, th = max(1, int(round(lw * fit))), max(1, int(round(lh * fit)))
    resized = logo.resize((tw, th), Image.LANCZOS)

    oy = (h - th) // 2
    if align == "left":
        ox = int(round((w - w * scale) / 2))
    elif align == "right":
        ox = w - tw - int(round((w - w * scale) / 2))
    else:
        ox = (w - tw) // 2

    has_alpha = region.shape[2] == 4
    base = Image.fromarray(region).convert("RGBA")
    layer = Image.new("RGBA", base.size, (0, 0, 0, 0))
    layer.paste(resized, (ox, oy))
    out = Image.alpha_composite(base, layer)
    out = np.array(out if has_alpha else out.convert("RGB"))
    return out, [ox, oy, tw, th]


def outside_diff(a, b, x, y, w, h):
    diff = np.any(a != b, axis=2)
    diff[y:y + h, x:x + w] = False
    return int(diff.sum())


def replace(image_path, logo_path, output_path, box_text, removal, scale, align):
    work, info, warnings = load_image(image_path)
    original = np.array(work)
    H, W = original.shape[:2]
    x, y, w, h = parse_box(box_text, W, H)

    cleaned, used_method = remove_logo(original, x, y, w, h, removal)
    patched, logo_rect = place_logo(cleaned, load_logo(logo_path), scale, align)

    result = original.copy()
    result[y:y + h, x:x + w] = patched

    if outside_diff(original, result, x, y, w, h) != 0:
        fail("Verifikasi gagal: ada piksel di luar area logo yang berubah.")

    # PNG lossless: tidak ada kompresi ulang yang dapat mengubah piksel lain.
    save_kwargs = {"optimize": False, "compress_level": 6}
    if info["icc_profile"]:
        save_kwargs["icc_profile"] = info["icc_profile"]
    if info["dpi"]:
        save_kwargs["dpi"] = info["dpi"]
    if info["exif"]:
        save_kwargs["exif"] = info["exif"]
    Image.fromarray(result).save(output_path, "PNG", **save_kwargs)

    # Verifikasi ulang dari file yang benar-benar tersimpan.
    saved = np.array(Image.open(output_path))
    if saved.shape != original.shape:
        fail("Verifikasi gagal: dimensi hasil berbeda dari gambar asli.")
    changed_outside = outside_diff(original, saved, x, y, w, h)
    if changed_outside != 0:
        fail("Verifikasi gagal: ada piksel di luar area logo yang berubah.")
    changed_inside = int(np.any(original[y:y + h, x:x + w] != saved[y:y + h, x:x + w], axis=2).sum())

    return {
        "ok": True,
        "width": W,
        "height": H,
        "box": [x, y, w, h],
        "logo_rect": [x + logo_rect[0], y + logo_rect[1], logo_rect[2], logo_rect[3]],
        "removal": used_method,
        "verification": {
            "outside_pixels_total": W * H - w * h,
            "outside_pixels_changed": changed_outside,
            "inside_pixels_changed": changed_inside,
            "dimensions_identical": True,
        },
        "warnings": warnings,
    }


def main():
    parser = argparse.ArgumentParser(description="Logo Corrector NTT")
    sub = parser.add_subparsers(dest="cmd", required=True)

    d = sub.add_parser("detect")
    d.add_argument("image")
    d.add_argument("logo")

    r = sub.add_parser("replace")
    r.add_argument("image")
    r.add_argument("logo")
    r.add_argument("output")
    r.add_argument("--box", required=True)
    r.add_argument("--removal", choices=["auto", "fill", "inpaint", "none"], default="auto")
    r.add_argument("--scale", type=float, default=0.92)
    r.add_argument("--align", choices=["center", "left", "right"], default="center")

    args = parser.parse_args()
    try:
        if args.cmd == "detect":
            result = detect(args.image, args.logo)
        else:
            scale = min(max(args.scale, 0.3), 1.0)
            result = replace(args.image, args.logo, args.output, args.box, args.removal, scale, args.align)
    except SystemExit:
        raise
    except Exception as exc:  # noqa: BLE001
        fail(f"Gagal memproses gambar: {exc}")
    print(json.dumps(result))


if __name__ == "__main__":
    main()
