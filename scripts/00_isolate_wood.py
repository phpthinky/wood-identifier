import cv2
import numpy as np
import json
import sys

# -------------------------------------------------------
# Step 1: Remove uniform background (white/light bg)
# -------------------------------------------------------

def remove_background_by_color(img, bg_threshold=240):
    """
    Flood fill from corners to detect background.
    More reliable than per-pixel threshold for circular subjects.
    """
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    h, w = gray.shape

    # Threshold — background is bright
    _, thresh = cv2.threshold(gray, bg_threshold, 255, cv2.THRESH_BINARY)

    # Flood fill from all 4 corners — captures connected background
    flood = thresh.copy()
    flood_mask = np.zeros((h + 2, w + 2), np.uint8)

    corners = [(0, 0), (w-1, 0), (0, h-1), (w-1, h-1)]
    for corner in corners:
        cv2.floodFill(flood, flood_mask, corner, 0)

    # What remains after flood fill = foreground (wood)
    fg_mask = np.where(flood == 0, 255, 0).astype(np.uint8)

    # Also include dark areas (bark) that weren't captured
    dark_mask = (gray < 50).astype(np.uint8) * 255
    fg_mask = cv2.bitwise_or(fg_mask, dark_mask)

    # Clean up
    kernel = np.ones((7, 7), np.uint8)
    fg_mask = cv2.morphologyEx(fg_mask, cv2.MORPH_CLOSE, kernel)
    fg_mask = cv2.morphologyEx(fg_mask, cv2.MORPH_OPEN, kernel)

    return fg_mask
# -------------------------------------------------------
# Step 2: Find largest contour = main wood subject
# -------------------------------------------------------

def find_wood_contour(fg_mask):
    """
    Find the largest contour in the foreground mask.
    This should be the main wood subject.
    Returns bounding rect and contour.
    """
    contours, _ = cv2.findContours(fg_mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    if not contours:
        return None, None

    # Largest contour = wood subject
    largest = max(contours, key=cv2.contourArea)
    area = cv2.contourArea(largest)

    # Reject if too small (< 10% of image)
    h, w = fg_mask.shape
    if area < (h * w * 0.10):
        return None, None

    x, y, bw, bh = cv2.boundingRect(largest)

    return largest, {"x": int(x), "y": int(y), "w": int(bw), "h": int(bh)}

# -------------------------------------------------------
# Step 3: GrabCut refinement
# -------------------------------------------------------

def refine_with_grabcut(img, bbox, iterations=5):
    """
    Use GrabCut to cleanly separate wood from background.
    bbox: initial bounding rect from contour detection.
    """
    x, y, w, h = bbox["x"], bbox["y"], bbox["w"], bbox["h"]

    # GrabCut needs rect with some margin
    margin = 5
    x = max(0, x - margin)
    y = max(0, y - margin)
    w = min(img.shape[1] - x, w + margin * 2)
    h = min(img.shape[0] - y, h + margin * 2)

    rect = (x, y, w, h)

    mask = np.zeros(img.shape[:2], np.uint8)
    bgd_model = np.zeros((1, 65), np.float64)
    fgd_model = np.zeros((1, 65), np.float64)

    cv2.grabCut(img, mask, rect, bgd_model, fgd_model, iterations, cv2.GC_INIT_WITH_RECT)

    # Combine definite and probable foreground
    fg_mask = np.where((mask == cv2.GC_FGD) | (mask == cv2.GC_PR_FGD), 255, 0).astype(np.uint8)

    return fg_mask

# -------------------------------------------------------
# Step 4: Apply isolation mask
# -------------------------------------------------------

def apply_isolation(img, mask):
    """Apply final mask — background becomes black"""
    isolated = cv2.bitwise_and(img, img, mask=mask)
    return isolated

# -------------------------------------------------------
# Step 5: Crop to bounding rect of isolated wood
# -------------------------------------------------------

def crop_to_subject(img, mask):
    """Crop image to tightest bounding box of isolated wood"""
    coords = cv2.findNonZero(mask)
    if coords is None:
        return img, mask

    x, y, w, h = cv2.boundingRect(coords)
    cropped_img  = img[y:y+h, x:x+w]
    cropped_mask = mask[y:y+h, x:x+w]

    return cropped_img, cropped_mask, {"x": int(x), "y": int(y), "w": int(w), "h": int(h)}

# -------------------------------------------------------
# Save isolated image
# -------------------------------------------------------

def save_isolated(img, output_path):
    cv2.imwrite(output_path, img)

# -------------------------------------------------------
# Main
# -------------------------------------------------------

def analyze(image_path, output_path=None, use_grabcut=False):
    img = cv2.imread(image_path)

    if img is None:
        return {"error": "Cannot read image", "path": image_path}

    h, w = img.shape[:2]
    original_size = {"width": w, "height": h}

    # Step 1: Remove background by color
    fg_mask = remove_background_by_color(img)

    bg_pixel_count = int(np.sum(fg_mask == 0))
    fg_pixel_count = int(np.sum(fg_mask == 255))
    bg_percentage  = round(bg_pixel_count / (h * w) * 100, 2)

    # Step 2: Find wood contour
    contour, bbox = find_wood_contour(fg_mask)

    if bbox is None:
        return {
            "error": "Could not isolate wood subject",
            "tip": "Ensure wood is clearly visible against background",
            "image": image_path,
            "bg_removed_percentage": bg_percentage
        }

    # Step 3: GrabCut refinement
    if use_grabcut:
        refined_mask = refine_with_grabcut(img, bbox)
    else:
        refined_mask = fg_mask

    # Step 4: Apply mask
    isolated = apply_isolation(img, refined_mask)

    # Step 5: Crop to subject
    cropped_img, cropped_mask, crop_coords = crop_to_subject(isolated, refined_mask)

    # Step 6: Save if output path given
    saved_path = None
    if output_path:
        save_isolated(cropped_img, output_path)
        saved_path = output_path

    ch, cw = cropped_img.shape[:2]

    return {
        "image": image_path,
        "original_size": original_size,
        "background_removed_pct": bg_percentage,
        "wood_bbox": bbox,
        "crop_coords": crop_coords,
        "isolated_size": {"width": int(cw), "height": int(ch)},
        "output_saved": saved_path,
        "isolation_method": "grabcut" if use_grabcut else "color_mask"
    }

if __name__ == "__main__":
    path = sys.argv[1]
    output = sys.argv[2] if len(sys.argv) > 2 else None
    result = analyze(path, output_path=output)
    print(json.dumps(result))
