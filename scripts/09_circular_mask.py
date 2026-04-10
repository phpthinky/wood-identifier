import cv2
import numpy as np
import json
import sys

class NumpyEncoder(json.JSONEncoder):
    def default(self, obj):
        if isinstance(obj, (np.integer,)):
            return int(obj)
        if isinstance(obj, (np.floating,)):
            return float(obj)
        if isinstance(obj, np.ndarray):
            return obj.tolist()
        return super().default(obj)
# -------------------------------------------------------
# Step 1: Detect circle boundary
# -------------------------------------------------------

def detect_circle(img):
    """
    Detect the main circular wood boundary using Hough Circle Transform.
    Returns (cx, cy, radius) or None if not detected.
    """
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

    # Take the first (most prominent) circle
    circle = np.round(circles[0][0]).astype(int)
    cx, cy, radius = circle[0], circle[1], circle[2]

    return (cx, cy, radius)

# -------------------------------------------------------
# Step 2: Apply circular mask
# -------------------------------------------------------

def apply_circular_mask(img, cx, cy, radius, edge_strip=0.10):
    """
    Mask out everything outside the circle.
    edge_strip: percentage of radius to remove from outer edge (bark removal)
                0.10 = remove outer 10% of radius
    """
    h, w = img.shape[:2]
    mask = np.zeros((h, w), dtype=np.uint8)

    # Inner radius after stripping bark edge
    inner_radius = int(radius * (1 - edge_strip))

    cv2.circle(mask, (cx, cy), inner_radius, 255, -1)

    # Apply mask — pixels outside circle become black
    masked = cv2.bitwise_and(img, img, mask=mask)

    return masked, mask, inner_radius

# -------------------------------------------------------
# Step 3: Extract dominant color from masked image
# -------------------------------------------------------

def get_dominant_from_mask(img, mask, k=3, brightness_min=30, brightness_max=240):
    """K-Means on masked pixels only"""
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

    # Only pixels inside circle AND within brightness range
    valid_mask = (mask == 255) & (gray >= brightness_min) & (gray <= brightness_max)
    pixels = img[valid_mask].astype(np.float32)

    if len(pixels) < k:
        return None

    criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 100, 0.2)
    _, labels, centers = cv2.kmeans(pixels, k, None, criteria, 10, cv2.KMEANS_RANDOM_CENTERS)

    counts = np.bincount(labels.flatten())
    sorted_idx = np.argsort(-counts)

    colors = []
    total = len(pixels)
    for i in sorted_idx:
        bgr = centers[i]
        rgb = (int(bgr[2]), int(bgr[1]), int(bgr[0]))
        colors.append({
            "rank": int(np.where(sorted_idx == i)[0][0]) + 1,
            "rgb": rgb,
            "hex": "#{:02x}{:02x}{:02x}".format(*rgb),
            "percentage": round((counts[i] / total) * 100, 2)
        })

    return colors

# -------------------------------------------------------
# Main
# -------------------------------------------------------

def analyze(image_path, edge_strip=0.10, k=3):
    img = cv2.imread(image_path)

    if img is None:
        return {"error": "Cannot read image", "path": image_path}

    h, w = img.shape[:2]

    # Step 1: Detect circle
    circle = detect_circle(img)

    if circle is None:
        return {
            "error": "No circle detected",
            "tip": "Ensure wood cross-section is clearly visible and well-lit",
            "image": image_path
        }

    cx, cy, radius = circle

    # Step 2: Apply mask + strip bark edge
    masked_img, mask, inner_radius = apply_circular_mask(img, cx, cy, radius, edge_strip)

    # Step 3: Dominant colors from clean wood area only
    dominant = get_dominant_from_mask(masked_img, mask, k=k)

    return {
        "image": image_path,
        "dimensions": {"width": w, "height": h},
        "circle": {
            "center": {"x": cx, "y": cy},
            "detected_radius": radius,
            "inner_radius": inner_radius,
            "edge_strip_percent": edge_strip * 100
        },
        "dominant_colors": dominant
    }

if __name__ == "__main__":
    path = sys.argv[1]
    edge_strip = float(sys.argv[2]) if len(sys.argv) > 2 else 0.10
    k = int(sys.argv[3]) if len(sys.argv) > 3 else 3
    result = analyze(path, edge_strip=edge_strip, k=k)
    print(json.dumps(result, cls=NumpyEncoder))
