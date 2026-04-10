import cv2
import numpy as np
import json
import sys

def get_dominant_colors_filtered(img, k=3, brightness_min=30, brightness_max=240):
    """
    K-Means dominant colors with brightness filter.
    Excludes near-black (shadows) and near-white (overexposed/background) pixels.
    brightness_min: ignore pixels darker than this (0-255)
    brightness_max: ignore pixels brighter than this (0-255)
    """

    # Convert to grayscale to measure brightness per pixel
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

    # Create mask — keep only pixels within brightness range
    mask = (gray >= brightness_min) & (gray <= brightness_max)

    # Apply mask — flatten to list of valid pixels only
    pixels = img[mask].astype(np.float32)

    if len(pixels) < k:
        return {"error": "Not enough valid pixels after filtering"}

    # K-Means
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

def analyze(image_path, k=3, brightness_min=30, brightness_max=240):
    img = cv2.imread(image_path)

    if img is None:
        return {"error": "Cannot read image", "path": image_path}

    dominant = get_dominant_colors_filtered(img, k=k,
        brightness_min=brightness_min,
        brightness_max=brightness_max)

    return {
        "image": image_path,
        "k_clusters": k,
        "brightness_filter": {
            "min": brightness_min,
            "max": brightness_max
        },
        "dominant_colors": dominant
    }

if __name__ == "__main__":
    path = sys.argv[1]
    k = int(sys.argv[2]) if len(sys.argv) > 2 else 3
    brightness_min = int(sys.argv[3]) if len(sys.argv) > 3 else 30
    brightness_max = int(sys.argv[4]) if len(sys.argv) > 4 else 240
    result = analyze(path, k=k, brightness_min=brightness_min, brightness_max=brightness_max)
    print(json.dumps(result))

