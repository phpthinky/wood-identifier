import cv2
import numpy as np
import json
import sys

def rgb_distance(rgb1, rgb2):
    """Simple Euclidean distance between two RGB colors"""
    return round(np.sqrt(sum((a - b) ** 2 for a, b in zip(rgb1, rgb2))), 2)

def get_region_dominant(img, region, brightness_min=30, brightness_max=240):
    """Returns rank 1 dominant color of a region"""
    h, w = img.shape[:2]

    if region == "center":
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
    else:
        y1, y2 = 0, h
        x1, x2 = 0, w

    cropped = img[y1:y2, x1:x2]
    gray = cv2.cvtColor(cropped, cv2.COLOR_BGR2GRAY)
    mask = (gray >= brightness_min) & (gray <= brightness_max)
    pixels = cropped[mask].astype(np.float32)

    if len(pixels) < 3:
        return None

    criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 100, 0.2)
    _, labels, centers = cv2.kmeans(pixels, 3, None, criteria, 10, cv2.KMEANS_RANDOM_CENTERS)

    counts = np.bincount(labels.flatten())
    dominant_idx = np.argmax(counts)
    bgr = centers[dominant_idx]
    rgb = (int(bgr[2]), int(bgr[1]), int(bgr[0]))

    return rgb

def analyze(image_path, threshold=30):
    """
    threshold: max acceptable RGB distance between regions
    below threshold = consistent photo = good for identification
    above threshold = inconsistent = warn user to retake photo
    """
    img = cv2.imread(image_path)

    if img is None:
        return {"error": "Cannot read image", "path": image_path}

    regions = ["center", "top", "bottom", "left", "right"]
    region_colors = {}

    for region in regions:
        rgb = get_region_dominant(img, region)
        if rgb:
            region_colors[region] = {
                "rgb": rgb,
                "hex": "#{:02x}{:02x}{:02x}".format(*rgb)
            }

    # Center is the reference
    center_rgb = region_colors["center"]["rgb"]

    # Compare all regions vs center
    comparisons = []
    distances = []

    for region, data in region_colors.items():
        if region == "center":
            continue
        dist = rgb_distance(center_rgb, data["rgb"])
        distances.append(dist)
        comparisons.append({
            "region": region,
            "rgb": data["rgb"],
            "hex": data["hex"],
            "distance_from_center": dist,
            "status": "ok" if dist <= threshold else "inconsistent"
        })

    max_distance = round(max(distances), 2)
    avg_distance = round(sum(distances) / len(distances), 2)

    # Overall photo quality
    if max_distance <= threshold:
        quality = "good"
        message = "Photo is consistent. Safe for identification."
    elif max_distance <= threshold * 2:
        quality = "fair"
        message = "Minor lighting variation detected. Results may vary."
    else:
        quality = "poor"
        message = "High color inconsistency. Please retake photo in better lighting."

    return {
        "image": image_path,
        "reference": {
            "region": "center",
            "rgb": center_rgb,
            "hex": "#{:02x}{:02x}{:02x}".format(*center_rgb)
        },
        "threshold": threshold,
        "max_distance": max_distance,
        "avg_distance": avg_distance,
        "quality": quality,
        "message": message,
        "comparisons": comparisons
    }

if __name__ == "__main__":
    path = sys.argv[1]
    threshold = int(sys.argv[2]) if len(sys.argv) > 2 else 30
    result = analyze(path, threshold=threshold)
    print(json.dumps(result))
