import cv2
import numpy as np
import json
import sys

# -------------------------------------------------------
# CIEDE2000 Implementation
# -------------------------------------------------------

def rgb_to_lab(rgb):
    """Convert RGB tuple to CIE Lab using OpenCV"""
    r, g, b = rgb
    # OpenCV expects BGR, shape (1,1,3)
    bgr = np.uint8([[[b, g, r]]])
    lab = cv2.cvtColor(bgr, cv2.COLOR_BGR2Lab)
    L, a, b_ = lab[0][0]
    # OpenCV Lab ranges: L=0-255, a=0-255, b=0-255
    # Convert to standard: L=0-100, a=-128-127, b=-128-127
    L = float(L) * 100 / 255
    a = float(a) - 128
    b_ = float(b_) - 128
    return (L, a, b_)

def ciede2000(lab1, lab2):
    """
    CIEDE2000 color difference formula.
    Returns delta E — lower = more similar.
    0-1   = imperceptible
    1-2   = perceptible on close observation
    2-10  = perceptible at a glance
    10+   = different colors
    """
    L1, a1, b1 = lab1
    L2, a2, b2 = lab2

    # Step 1: C and h
    C1 = np.sqrt(a1**2 + b1**2)
    C2 = np.sqrt(a2**2 + b2**2)
    C_avg = (C1 + C2) / 2
    C_avg7 = C_avg**7
    G = 0.5 * (1 - np.sqrt(C_avg7 / (C_avg7 + 25**7)))
    a1p = a1 * (1 + G)
    a2p = a2 * (1 + G)
    C1p = np.sqrt(a1p**2 + b1**2)
    C2p = np.sqrt(a2p**2 + b2**2)

    h1p = np.degrees(np.arctan2(b1, a1p)) % 360
    h2p = np.degrees(np.arctan2(b2, a2p)) % 360

    # Step 2: Delta L, C, H
    dLp = L2 - L1
    dCp = C2p - C1p

    if C1p * C2p == 0:
        dhp = 0
    elif abs(h2p - h1p) <= 180:
        dhp = h2p - h1p
    elif h2p - h1p > 180:
        dhp = h2p - h1p - 360
    else:
        dhp = h2p - h1p + 360

    dHp = 2 * np.sqrt(C1p * C2p) * np.sin(np.radians(dhp / 2))

    # Step 3: CIEDE2000
    Lp_avg = (L1 + L2) / 2
    Cp_avg = (C1p + C2p) / 2

    if C1p * C2p == 0:
        hp_avg = h1p + h2p
    elif abs(h1p - h2p) <= 180:
        hp_avg = (h1p + h2p) / 2
    elif h1p + h2p < 360:
        hp_avg = (h1p + h2p + 360) / 2
    else:
        hp_avg = (h1p + h2p - 360) / 2

    T = (1
         - 0.17 * np.cos(np.radians(hp_avg - 30))
         + 0.24 * np.cos(np.radians(2 * hp_avg))
         + 0.32 * np.cos(np.radians(3 * hp_avg + 6))
         - 0.20 * np.cos(np.radians(4 * hp_avg - 63)))

    SL = 1 + 0.015 * (Lp_avg - 50)**2 / np.sqrt(20 + (Lp_avg - 50)**2)
    SC = 1 + 0.045 * Cp_avg
    SH = 1 + 0.015 * Cp_avg * T

    d_theta = 30 * np.exp(-((hp_avg - 275) / 25)**2)
    Cp_avg7 = Cp_avg**7
    RC = 2 * np.sqrt(Cp_avg7 / (Cp_avg7 + 25**7))
    RT = -np.sin(np.radians(2 * d_theta)) * RC

    dE = np.sqrt(
        (dLp / SL)**2 +
        (dCp / SC)**2 +
        (dHp / SH)**2 +
        RT * (dCp / SC) * (dHp / SH)
    )

    return round(float(dE), 4)

# -------------------------------------------------------
# Reference wood colors (sample data — same structure
# as your DB will provide later via Laravel)
# -------------------------------------------------------

WOOD_REFERENCES = [
    {"species": "Narra",        "hex": "#c8a96e"},
    {"species": "Mahogany",     "hex": "#8b4513"},
    {"species": "Molave",       "hex": "#c19a6b"},
    {"species": "Ipil",         "hex": "#7b5e3a"},
    {"species": "Yakal",        "hex": "#6b4226"},
    {"species": "Tindalo",      "hex": "#b5651d"},
    {"species": "Kamagong",     "hex": "#2b1a0e"},
    {"species": "Lauan",        "hex": "#d4a574"},
    
]

def hex_to_rgb(hex_str):
    hex_str = hex_str.lstrip("#")
    return tuple(int(hex_str[i:i+2], 16) for i in (0, 2, 4))

def get_center_dominant(img, brightness_min=30, brightness_max=240):
    h, w = img.shape[:2]
    y1, y2 = h // 4, 3 * h // 4
    x1, x2 = w // 4, 3 * w // 4
    cropped = img[y1:y2, x1:x2]

    gray = cv2.cvtColor(cropped, cv2.COLOR_BGR2GRAY)
    mask = (gray >= brightness_min) & (gray <= brightness_max)
    pixels = cropped[mask].astype(np.float32)

    criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 100, 0.2)
    _, labels, centers = cv2.kmeans(pixels, 3, None, criteria, 10, cv2.KMEANS_RANDOM_CENTERS)

    counts = np.bincount(labels.flatten())
    dominant_idx = np.argmax(counts)
    bgr = centers[dominant_idx]

    return (int(bgr[2]), int(bgr[1]), int(bgr[0]))

def analyze(image_path):
    img = cv2.imread(image_path)

    if img is None:
        return {"error": "Cannot read image", "path": image_path}

    # Get center dominant color
    sample_rgb = get_center_dominant(img)
    sample_hex = "#{:02x}{:02x}{:02x}".format(*sample_rgb)
    sample_lab = rgb_to_lab(sample_rgb)

    # Compare vs all references
    matches = []
    for ref in WOOD_REFERENCES:
        ref_rgb = hex_to_rgb(ref["hex"])
        ref_lab = rgb_to_lab(ref_rgb)
        delta_e = ciede2000(sample_lab, ref_lab)
        matches.append({
            "species": ref["species"],
            "ref_hex": ref["hex"],
            "delta_e": delta_e,
        })

    # Sort by closest match
    matches.sort(key=lambda x: x["delta_e"])

    # Top match
    top = matches[0]

    return {
        "image": image_path,
        "sample": {
            "rgb": sample_rgb,
            "hex": sample_hex,
        },
        "top_match": top,
        "all_matches": matches
    }

if __name__ == "__main__":
    path = sys.argv[1]
    result = analyze(path)
    print(json.dumps(result))
