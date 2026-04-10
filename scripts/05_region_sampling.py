import cv2
import numpy as np
import json
import sys

def get_dominant_colors_filtered(pixels, k=3):
    if len(pixels) < k:
        return {"error": "Not enough valid pixels"}

    criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 100, 0.2)
    _, labels, centers = cv2.kmeans(pixels, k, None, criteria, 10, cv2.KMEANS_RANDOM_CENTERS)

    counts = np.bincount(labels.flatten())
    sorted_idx = np.argsort(-counts)

    colors = []
    total_pixels = len(pixels)

    for i in sorted_idx:
        bgr = centers[i]
        rgb = (int(bgr[2]), int(bgr[1]), int(bgr[0]))
        hex_color = "#{:02x}{:02x}{:02x}".format(*rgb)
        percentage = round((counts[i] / total_pixels) * 100, 2)
        colors.append({
            "rank": int(np.where(sorted_idx == i)[0][0]) + 1,
            "rgb": rgb,
            "hex": hex_color,
            "percentage": percentage,
        })

    return colors

def sample_region(img, region, k=3, brightness_min=30, brightness_max=240):
    """
    region: 'full' | 'center' | 'top' | 'bottom' | 'left' | 'right'
    center = middle 50% of image — most representative wood area
    """
    h, w = img.shape[:2]

    if region == "center":
        # Crop middle 50%
        y1, y2 = h // 4, 3 * h // 4
        x1, x2 = w // 4, 3 * w // 4
    elif region == "top":
        y1, y2 = 0, h // 2
        x1, x2 = 0, w
    elif region == "bottom":
        y1, y2 = h // 2, h
        x1, x2 = 0, w
    elif region == "left":
        y1, y2 = 0, h
        x1, x2 = 0, w // 2
    elif region == "right":
        y1, y2 = 0, h
        x1, x2 = w // 2, w
    else:  # full
        y1, y2 = 0, h
        x1, x2 = 0, w

    cropped = img[y1:y2, x1:x2]

    # Brightness filter
    gray = cv2.cvtColor(cropped, cv2.COLOR_BGR2GRAY)
    mask = (gray >= brightness_min) & (gray <= brightness_max)
    pixels = cropped[mask].astype(np.float32)

    dominant = get_dominant_colors_filtered(pixels, k=k)

    return {
        "region": region,
        "crop_coords": {"x1": x1, "y1": y1, "x2": x2, "y2": y2},
        "dominant_colors": dominant
    }

def analyze(image_path, k=3):
    img = cv2.imread(image_path)

    if img is None:
        return {"error": "Cannot read image", "path": image_path}

    h, w = img.shape[:2]
    regions = ["full", "center", "top", "bottom", "left", "right"]

    results = []
    for region in regions:
        results.append(sample_region(img, region, k=k))

    return {
        "image": image_path,
        "dimensions": {"width": w, "height": h},
        "k_clusters": k,
        "regions": results
    }

if __name__ == "__main__":
    path = sys.argv[1]
    k = int(sys.argv[2]) if len(sys.argv) > 2 else 3
    result = analyze(path, k=k)
    print(json.dumps(result))
