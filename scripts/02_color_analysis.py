import cv2
import numpy as np
import json
import sys

def analyze(image_path):
    img = cv2.imread(image_path)

    if img is None:
        return {"error": "Cannot read image", "path": image_path}

    height, width, channels = img.shape

    # --- Color Analysis ---

    # 1. Average BGR color ng buong image
    avg_bgr = cv2.mean(img)[:3]  # returns (B, G, R, alpha) — slice to 3

    # 2. Convert to RGB (OpenCV reads as BGR by default)
    avg_rgb = (int(avg_bgr[2]), int(avg_bgr[1]), int(avg_bgr[0]))

    # 3. Convert to HSV para sa color classification
    hsv_img = cv2.cvtColor(img, cv2.COLOR_BGR2HSV)
    avg_hsv = cv2.mean(hsv_img)[:3]

    # 4. Convert average RGB to hex
    hex_color = "#{:02x}{:02x}{:02x}".format(*avg_rgb)

    return {
        "width": width,
        "height": height,
        "channels": channels,
        "total_pixels": width * height,
        "color": {
            "avg_rgb": avg_rgb,
            "avg_hsv": {
                "h": round(avg_hsv[0], 2),
                "s": round(avg_hsv[1], 2),
                "v": round(avg_hsv[2], 2),
            },
            "hex": hex_color,
        }
    }

if __name__ == "__main__":
    path = sys.argv[1]
    result = analyze(path)
    print(json.dumps(result))
