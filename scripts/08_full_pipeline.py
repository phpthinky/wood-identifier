import cv2
import numpy as np
import json
import sys

# -------------------------------------------------------
# Lab conversion
# -------------------------------------------------------

def rgb_to_lab(rgb):
    r, g, b = rgb
    bgr = np.uint8([[[b, g, r]]])
    lab = cv2.cvtColor(bgr, cv2.COLOR_BGR2Lab)
    L, a, b_ = lab[0][0]
    L = float(L) * 100.0 / 255.0
    a = float(a) - 128.0
    b_ = float(b_) - 128.0
    return (L, a, b_)

# -------------------------------------------------------
# CIEDE2000
# -------------------------------------------------------

def ciede2000(lab1, lab2):
    L1, a1, b1 = float(lab1[0]), float(lab1[1]), float(lab1[2])
    L2, a2, b2 = float(lab2[0]), float(lab2[1]), float(lab2[2])

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
# Reference colors (will come from Laravel/DB later)
# -------------------------------------------------------

WOOD_REFERENCES = [
    {"species": "Narra",     "hex": "#c8a96e"},
    {"species": "Mahogany",  "hex": "#8b4513"},
    {"species": "Molave",    "hex": "#c19a6b"},
    {"species": "Ipil",      "hex": "#7b5e3a"},
    {"species": "Yakal",     "hex": "#6b4226"},
    {"species": "Tindalo",   "hex": "#b5651d"},
    {"species": "Kamagong",  "hex": "#2b1a0e"},
    {"species": "Lauan",     "hex": "#d4a574"},
    {"species": "Oak",       "hex": "#c8a87a"},
    {"species": "Oak (White)",  "hex": "#f0dfc0"},
    {"species": "Oak (Red)",    "hex": "#c8a87a"},
    {"species": "Oak (Aged)",   "hex": "#b8864e"},
]

def hex_to_rgb(hex_str):
    hex_str = hex_str.lstrip("#")
    return tuple(int(hex_str[i:i+2], 16) for i in (0, 2, 4))

# -------------------------------------------------------
# Photo quality check
# -------------------------------------------------------

def check_quality(img, threshold=30):
    def get_dominant(crop):
        gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
        mask = (gray >= 30) & (gray <= 240)
        pixels = crop[mask].astype(np.float32)
        if len(pixels) < 3:
            return None
        criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 100, 0.2)
        _, labels, centers = cv2.kmeans(pixels, 3, None, criteria, 10, cv2.KMEANS_RANDOM_CENTERS)
        counts = np.bincount(labels.flatten())
        bgr = centers[np.argmax(counts)]
        return (int(bgr[2]), int(bgr[1]), int(bgr[0]))

    h, w = img.shape[:2]
    center = img[h//4:3*h//4, w//4:3*w//4]
    top    = img[0:h//2, 0:w]
    bottom = img[h//2:h, 0:w]

    c_rgb = get_dominant(center)
    t_rgb = get_dominant(top)
    b_rgb = get_dominant(bottom)

    if not all([c_rgb, t_rgb, b_rgb]):
        return {"quality": "unknown", "message": "Could not evaluate photo quality"}

    def dist(a, b):
        return np.sqrt(sum((x-y)**2 for x, y in zip(a, b)))

    max_dist = max(dist(c_rgb, t_rgb), dist(c_rgb, b_rgb))

    if max_dist <= threshold:
        quality = "good"
        message = "Photo is consistent. Safe for identification."
    elif max_dist <= threshold * 2:
        quality = "fair"
        message = "Minor lighting variation detected. Results may vary."
    else:
        quality = "poor"
        message = "High color inconsistency. Retake photo in better lighting."

    return {
        "quality": quality,
        "message": message,
        "max_distance": round(float(max_dist), 2)
    }

# -------------------------------------------------------
# Get sample color (center, brightness filtered)
# -------------------------------------------------------

def get_sample_color(img):
    h, w = img.shape[:2]
    center = img[h//4:3*h//4, w//4:3*w//4]

    gray = cv2.cvtColor(center, cv2.COLOR_BGR2GRAY)
    mask = (gray >= 30) & (gray <= 240)
    pixels = center[mask].astype(np.float32)

    if len(pixels) < 3:
        return None

    criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 100, 0.2)
    _, labels, centers = cv2.kmeans(pixels, 3, None, criteria, 10, cv2.KMEANS_RANDOM_CENTERS)
    counts = np.bincount(labels.flatten())
    bgr = centers[np.argmax(counts)]
    rgb = (int(bgr[2]), int(bgr[1]), int(bgr[0]))

    return rgb

# -------------------------------------------------------
# Confidence rating
# -------------------------------------------------------

def confidence_rating(delta_e):
    if delta_e <= 2:
        return "very_high"
    elif delta_e <= 5:
        return "high"
    elif delta_e <= 10:
        return "medium"
    elif delta_e <= 20:
        return "low"
    else:
        return "very_low"

# -------------------------------------------------------
# Main pipeline
# -------------------------------------------------------

def analyze(image_path, references=None):
    img = cv2.imread(image_path)

    if img is None:
        return {"error": "Cannot read image", "path": image_path}

    refs = references if references else WOOD_REFERENCES

    # Step 1: Quality check
    quality = check_quality(img)

    # Step 2: Sample color
    sample_rgb = get_sample_color(img)
    if sample_rgb is None:
        return {"error": "Could not extract sample color", "quality": quality}

    sample_hex = "#{:02x}{:02x}{:02x}".format(*sample_rgb)
    sample_lab = rgb_to_lab(sample_rgb)

    # Step 3: CIEDE2000 match
    matches = []
    for ref in refs:
        ref_rgb = hex_to_rgb(ref["hex"])
        ref_lab = rgb_to_lab(ref_rgb)
        delta_e = ciede2000(sample_lab, ref_lab)
        matches.append({
            "species": ref["species"],
            "ref_hex": ref["hex"],
            "delta_e": delta_e,
            "confidence": confidence_rating(delta_e)
        })

    matches.sort(key=lambda x: x["delta_e"])
    top = matches[0]

    # Step 4: Recommendation
    if quality["quality"] == "poor":
        recommendation = "retake"
        advice = "Poor photo quality. Please retake in better lighting before identification."
    elif top["confidence"] in ["very_low", "low"]:
        recommendation = "use_ai"
        advice = "Low color match confidence. Recommend Claude Vision for verification."
    elif top["confidence"] == "medium":
        recommendation = "verify"
        advice = "Medium confidence. Consider Claude Vision to confirm."
    else:
        recommendation = "accept"
        advice = "High confidence match. CIEDE2000 result is reliable."

    return {
        "image": image_path,
        "quality": quality,
        "sample": {
            "rgb": sample_rgb,
            "hex": sample_hex,
        },
        "top_match": top,
        "top_3": matches[:3],
        "all_matches": matches,
        "recommendation": recommendation,
        "advice": advice
    }

if __name__ == "__main__":
    path = sys.argv[1]
    result = analyze(path)
    print(json.dumps(result))
