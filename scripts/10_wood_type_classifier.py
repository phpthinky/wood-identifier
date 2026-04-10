import cv2
import numpy as np
import json
import sys

# -------------------------------------------------------
# Circle detection
# -------------------------------------------------------

def detect_circle(img):
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    blurred = cv2.GaussianBlur(gray, (9, 9), 2)

    h, w = img.shape[:2]
    min_radius = min(h, w) // 4
    max_radius = min(h, w) // 2

    circles = cv2.HoughCircles(
        blurred,
        cv2.HOUGH_GRADIENT,
        dp=1.2,
        minDist=min(h, w) // 2,
        param1=50,
        param2=30,
        minRadius=min_radius,
        maxRadius=max_radius
    )

    if circles is None:
        return None

    circle = np.round(circles[0][0]).astype(int)
    return (int(circle[0]), int(circle[1]), int(circle[2]))

# -------------------------------------------------------
# Texture analysis
# -------------------------------------------------------

def analyze_texture(img):
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    h, w = gray.shape
    center = gray[h//4:3*h//4, w//4:3*w//4]

    std_dev = float(center.std())
    mean_brightness = float(center.mean())
    laplacian = cv2.Laplacian(center, cv2.CV_64F)
    laplacian_var = float(laplacian.var())

    return {
        "std_dev": round(std_dev, 2),
        "mean_brightness": round(mean_brightness, 2),
        "laplacian_var": round(laplacian_var, 2)
    }

# -------------------------------------------------------
# Saturation analysis
# -------------------------------------------------------

def analyze_saturation(img):
    hsv = cv2.cvtColor(img, cv2.COLOR_BGR2HSV)
    h, w = img.shape[:2]
    center_hsv = hsv[h//4:3*h//4, w//4:3*w//4]

    sat = center_hsv[:, :, 1].astype(np.float32)
    return {
        "sat_std": round(float(sat.std()), 2),
        "sat_mean": round(float(sat.mean()), 2)
    }

# -------------------------------------------------------
# Directionality analysis (Sobel X vs Y)
# -------------------------------------------------------

def analyze_directionality(img):
    """
    Sobel X vs Y comparison.
    side_cut:  one direction dominates (grain lines)
    flat_cut:  balanced (dot/pore pattern, no dominant direction)
    plywood:   alternating layers = multiple directions = high both X and Y
                but with visible horizontal banding
    """
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    h, w = gray.shape
    center = gray[h//4:3*h//4, w//4:3*w//4]

    sobel_x = cv2.Sobel(center, cv2.CV_64F, 1, 0, ksize=3)
    sobel_y = cv2.Sobel(center, cv2.CV_64F, 0, 1, ksize=3)

    energy_x = float(np.mean(sobel_x ** 2))
    energy_y = float(np.mean(sobel_y ** 2))

    total = energy_x + energy_y
    ratio = round(energy_x / energy_y, 4) if energy_y > 0 else 999.0

    # Directionality score: 1.0 = perfectly balanced, >> 1 or << 1 = directional
    # side_cut: ratio far from 1.0
    # flat_cut: ratio close to 1.0
    # plywood:  ratio close to 1.0 BUT with horizontal banding pattern

    return {
        "energy_x": round(energy_x, 2),
        "energy_y": round(energy_y, 2),
        "ratio_x_to_y": ratio,
        "directional": ratio > 1.8 or ratio < 0.55  # True = side_cut candidate
    }

# -------------------------------------------------------
# Plywood layer detection
# -------------------------------------------------------

def detect_plywood_layers(img):
    """
    Plywood has alternating horizontal bands of different grain direction.
    Detect via horizontal variance profile — peaks = layer boundaries.
    """
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    h, w = gray.shape

    # Row-wise standard deviation — peaks at layer boundaries
    row_std = np.array([gray[r, :].std() for r in range(h)])

    # Smooth the profile
    kernel = np.ones(10) / 10
    smoothed = np.convolve(row_std, kernel, mode='same')

    # Count peaks (layer boundaries)
    mean_std = smoothed.mean()
    peaks = 0
    for i in range(1, len(smoothed) - 1):
        if smoothed[i] > smoothed[i-1] and smoothed[i] > smoothed[i+1]:
            if smoothed[i] > mean_std * 1.2:
                peaks += 1

    # Plywood typically has 3+ visible layer boundaries
    is_plywood = peaks >= 3

    return {
        "layer_boundary_peaks": int(peaks),
        "is_plywood": is_plywood
    }

# -------------------------------------------------------
# Wood type classification
# -------------------------------------------------------

def classify_wood_type(circle, texture, saturation, directionality, plywood):
    TEXTURE_STD_PAINTED = 10.0
    LAPLACIAN_PAINTED   = 20.0
    SAT_STD_PAINTED     = 8.0

    # 1. Cross-section
    if circle is not None:
        return {
            "wood_type": "cross_section",
            "forensic_flag": False,
            "confidence": "high",
            "advice": "Cross-section detected. Use circular mask for color sampling."
        }

    std_dev   = texture["std_dev"]
    laplacian = texture["laplacian_var"]
    sat_std   = saturation["sat_std"]

    # 2. Painted — low everything
    if std_dev < TEXTURE_STD_PAINTED and laplacian < LAPLACIAN_PAINTED and sat_std < SAT_STD_PAINTED:
        return {
            "wood_type": "painted",
            "forensic_flag": True,
            "confidence": "high",
            "advice": "Low texture and saturation variance. Surface appears painted. Flag for inspection."
        }

    # 3. Plywood — layer boundaries detected
    if plywood["is_plywood"]:
        return {
            "wood_type": "plywood",
            "forensic_flag": True,
            "confidence": "high",
            "advice": "Layered material detected. Cannot identify as solid wood species. Flag for inspection."
        }

    # 4. Side cut — directional grain
    if directionality["directional"] and laplacian >= LAPLACIAN_PAINTED:
        return {
            "wood_type": "side_cut",
            "forensic_flag": False,
            "confidence": "high",
            "advice": "Natural grain pattern detected. Side cut or flat surface wood."
        }

    # 5. Flat cut — balanced, non-directional, high texture
    if not directionality["directional"] and laplacian >= LAPLACIAN_PAINTED:
        return {
            "wood_type": "flat_cut",
            "forensic_flag": False,
            "confidence": "medium",
            "advice": "Non-directional texture detected. Likely end grain or flat cut lumber."
        }

    # 6. Uncertain
    return {
        "wood_type": "uncertain",
        "forensic_flag": False,
        "confidence": "low",
        "advice": "Cannot determine wood surface type. Recommend Claude Vision for verification."
    }

# -------------------------------------------------------
# Main
# -------------------------------------------------------

def analyze(image_path):
    img = cv2.imread(image_path)

    if img is None:
        return {"error": "Cannot read image", "path": image_path}

    h, w = img.shape[:2]

    circle        = detect_circle(img)
    texture       = analyze_texture(img)
    saturation    = analyze_saturation(img)
    directionality = analyze_directionality(img)
    plywood       = detect_plywood_layers(img)

    classification = classify_wood_type(circle, texture, saturation, directionality, plywood)

    return {
        "image": image_path,
        "dimensions": {"width": w, "height": h},
        "circle_detected": circle is not None,
        "circle": {"x": circle[0], "y": circle[1], "radius": circle[2]} if circle else None,
        "texture": texture,
        "saturation": saturation,
        "directionality": directionality,
        "plywood": plywood,
        "classification": classification
    }

if __name__ == "__main__":
    path = sys.argv[1]
    result = analyze(path)
    print(json.dumps(result))