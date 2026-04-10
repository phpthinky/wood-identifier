import cv2
import numpy as np
import json
import sys

def analyze(image_path):
    img = cv2.imread(image_path)

    if img is None:
        return {"error": "Cannot read image", "path": image_path}

    height, width, channels = img.shape

    return {
        "width": width,
        "height": height,
        "channels": channels,
        "total_pixels": width * height,
    }

if __name__ == "__main__":
    path = sys.argv[1]
    result = analyze(path)
    print(json.dumps(result))
