import cv2
import numpy as np
import json
import sys

def get_dominant_colors(img, k=3):
    # Reshape image to list of pixels
    pixels = img.reshape(-1, 3).astype(np.float32)

    # K-Means clustering
    criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 100, 0.2)
    _, labels, centers = cv2.kmeans(pixels, k, None, criteria, 10, cv2.KMEANS_RANDOM_CENTERS)

    # Count pixels per cluster
    counts = np.bincount(labels.flatten())

    # Sort by dominance (most pixels first)
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

def analyze(image_path, k=3):
    img = cv2.imread(image_path)

    if img is None:
        return {"error": "Cannot read image", "path": image_path}

    dominant = get_dominant_colors(img, k=k)

    return {
        "image": image_path,
        "k_clusters": k,
        "dominant_colors": dominant
    }

if __name__ == "__main__":
    path = sys.argv[1]
    k = int(sys.argv[2]) if len(sys.argv) > 2 else 3
    result = analyze(path, k=k)
    print(json.dumps(result))
