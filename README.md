# Wood Anatomy - OpenCV Learning Scripts

Progressive learning scripts from basic to advanced.
Each script is standalone and reviewable independently.

---

## Scripts

### 01_image_info.py
**Topic:** Basic image reading and dimensions
**Learn:** cv2.imread(), img.shape, JSON output via stdout
**Usage:**
```bash
python3 scripts/01_image_info.py /path/to/image.png
```
**Output:** width, height, channels, total_pixels

---

### 02_color_analysis.py
**Topic:** Average color extraction + HSV conversion
**Learn:** cv2.mean(), cv2.cvtColor(), BGR to RGB, RGB to hex
**Usage:**
```bash
python3 scripts/02_color_analysis.py /path/to/image.png
```
**Output:** avg_rgb, avg_hsv, hex

---

### 03_dominant_colors.py
**Topic:** K-Means color clustering (raw, no filter)
**Learn:** cv2.kmeans(), pixel reshaping, color ranking by percentage
**Usage:**
```bash
python3 scripts/03_dominant_colors.py /path/to/image.png
python3 scripts/03_dominant_colors.py /path/to/image.png 5  # 5 clusters
```
**Output:** dominant_colors ranked by percentage
**Lesson:** Raw K-Means picks up background noise (white bg = rank 3)

---

### 04_filtered_dominant_colors.py
**Topic:** K-Means with brightness filter
**Learn:** Grayscale mask, pixel filtering before clustering
**Usage:**
```bash
python3 scripts/04_filtered_dominant_colors.py /path/to/image.png
python3 scripts/04_filtered_dominant_colors.py /path/to/image.png 3 30 240
# args: path, k_clusters, brightness_min, brightness_max
```
**Output:** dominant_colors (background/shadow excluded)
**Lesson:** brightness_min=30 removes shadows, brightness_max=240 removes white bg

---

### 05_region_sampling.py
**Topic:** Per-region dominant color extraction
**Learn:** Image slicing (center, top, bottom, left, right crops)
**Usage:**
```bash
python3 scripts/05_region_sampling.py /path/to/image.png
python3 scripts/05_region_sampling.py /path/to/image.png 5  # 5 clusters
```
**Output:** dominant_colors per region
**Lesson:** Center crop is most representative — least background influence

---

### 06_color_consistency.py
**Topic:** Photo quality checker via color variance
**Learn:** RGB Euclidean distance, region comparison
**Usage:**
```bash
python3 scripts/06_color_consistency.py /path/to/image.png
python3 scripts/06_color_consistency.py /path/to/image.png 30  # threshold
```
**Output:** quality (good/fair/poor), max_distance, per-region status
**Lesson:** Center is reference. If edges differ too much = bad lighting = retake

---

### 07_ciede2000_match.py
**Topic:** CIEDE2000 color difference formula
**Learn:** RGB → Lab conversion, CIEDE2000 math, species matching
**Usage:**
```bash
python3 scripts/07_ciede2000_match.py /path/to/image.png
```
**Output:** sample color, all species ranked by delta_e
**Lesson:** Lower delta_e = closer match. One hex per species is wrong — need multi-reference
**Bug fixed:** numpy uint8 overflow → cast to float64 before Lab math

---

### 08_full_pipeline.py
**Topic:** Complete CIEDE2000 identification pipeline
**Learn:** Pipeline composition, confidence rating, recommendation logic
**Usage:**
```bash
python3 scripts/08_full_pipeline.py /path/to/image.png
```
**Output:** quality + sample + top_match + top_3 + recommendation + advice
**Lesson:**
- Multi-reference per species = accurate (White Oak #f0dfc0 vs Oak #c8a87a)
- delta_e 0.49 = very_high confidence (White Oak confirmed)
- Recommendation drives dual-engine: accept / verify / use_ai / retake
**References:** Hardcoded for learning — in production comes from Laravel DB

---

### 09_circular_mask.py
**Topic:** Circular wood cross-section isolation
**Learn:** HoughCircles, circular masking, bark edge stripping
**Usage:**
```bash
python3 scripts/09_circular_mask.py /path/to/image.png
python3 scripts/09_circular_mask.py /path/to/image.png 0.15 3
# args: path, edge_strip (0.0-1.0), k_clusters
```
**Output:** circle coords, inner_radius, dominant_colors (bark excluded)
**Lesson:**
- edge_strip=0.10 removes outer 10% (bark ring)
- No circle detected = not a cross-section cut
- Brightness filter still needed inside circle (dark pith, watermarks)
**Bug fixed:** numpy int64 not JSON serializable → NumpyEncoder class

---

### 10_wood_type_classifier.py
**Topic:** Wood surface type detection
**Learn:** Texture variance, Sobel directionality, plywood layer detection
**Usage:**
```bash
python3 scripts/10_wood_type_classifier.py /path/to/image.png
```
**Output:** wood_type, forensic_flag, confidence, advice
**Wood types detected:**
- cross_section → circular cut, annual rings (HoughCircles)
- side_cut      → directional grain (Sobel X vs Y ratio)
- flat_cut      → non-directional, end grain
- plywood       → layer boundary peaks via row std profile
- painted       → low texture + low saturation variance
- uncertain     → Claude Vision recommended
**Lesson:**
- Plywood face view = indistinguishable from side_cut (veneer surface)
- Edge view needed to detect layers reliably
- Annual rings fool layer detector (peaks from rings ≠ plywood layers)
- forensic_flag = true for painted and plywood

---

### 00_isolate_wood.py
**Topic:** Wood subject isolation from background
**Learn:** Flood fill background removal, contour detection, GrabCut
**Usage:**
```bash
python3 scripts/00_isolate_wood.py /path/to/image.png
python3 scripts/00_isolate_wood.py /path/to/image.png /path/to/output.png
```
**Output:** isolation result, bg_removed_pct, isolated_size, saved output
**Lesson:**
- Flood fill from corners more reliable than per-pixel threshold for circular wood
- GrabCut works best for rectangular subjects, not circular
- Must run BEFORE classification and color analysis
- cut_wood (already cropped) = no background to remove

---

## Pipeline Order (Production)

```
00_isolate_wood.py          ← isolate subject first
    ↓
10_wood_type_classifier.py  ← detect cut type
    ↓
09_circular_mask.py         ← if cross_section
08_full_pipeline.py         ← if side_cut / flat_cut
    ↓
Claude Vision               ← if uncertain / low confidence
    ↓
Weight filter               ← hardwood / softwood (optional)
Smell filter                ← expert multi-select (optional)
    ↓
Final species match
```

---

## Key Lessons Learned

| Problem | Cause | Fix |
|---|---|---|
| cv2.imread returns None | Empty file / wrong path | Always use absolute path from Laravel |
| NaN in CIEDE2000 | numpy uint8 overflow | Cast to float64 before math |
| int64 JSON error | numpy types not serializable | NumpyEncoder class |
| White background in K-Means | No brightness filter | brightness_max=240 filter |
| Wrong species match | Single hex reference | Multi-hex per species |
| Annual rings = plywood false positive | Row std peaks from rings | Needs circle check first |
| Plywood face = side_cut | Veneer hides layers | Edge view photo needed |

---

## Laravel Integration Pattern

```
Controller
  → shell_exec("python3 scripts/08_full_pipeline.py {$fullPath} 2>&1")
  → json_decode($output)
  → return response()->json($result)

Python script
  → always print(json.dumps(result)) ← stdout only
  → errors → {"error": "..."} ← never raw exceptions to stdout
  → always absolute path via sys.argv[1]
```

---

## Environment
- Python 3.12
- OpenCV 4.13.0
- NumPy 2.4.4
- Linux Mint 22
- Laravel 12 (caller)
- NativePHP Mobile (Android target)

---

## Wood Identification Tree Diagram

```
START
│
├── 1. Grain Pattern Detection
│   │
│   ├── Straight Grain
│   │   ├── Uniform → Likely Softwood / Plantation Wood
│   │   └── Fine & Tight → Possible Hardwood (e.g., maple-type)
│   │
│   ├── Wavy / Interlocked Grain
│   │   └── Likely Tropical Hardwood (e.g., teak/mahogany group)
│   │
│   └── Irregular / Figured Grain
│       └── Decorative / Premium Wood (burl, curly, flame)
│
├── 2. Pore Visibility (Critical Classifier)
│   │
│   ├── Open Pores (Visible holes)
│   │   ├── Ring-porous (pores in lines)
│   │   │   └── Oak / Ash group
│   │   │
│   │   └── Diffuse-porous (evenly spread pores)
│   │       └── Tropical hardwood group
│   │
│   └── Closed Pores (No visible holes)
│       └── Softwood or fine hardwood (pine/maple-type)
│
├── 3. Growth Ring Analysis
│   │
│   ├── Strong / High Contrast Rings
│   │   └── Fast-growing wood (common lumber)
│   │
│   └── Faint / Tight Rings
│       └── Dense / slow-growing hardwood
│
├── 4. Ray Pattern Detection (Advanced Feature)
│   │
│   ├── Visible Rays (shiny lines across grain)
│   │   └── Oak-type woods
│   │
│   └── No Visible Rays
│       └── Most other species
│
├── 5. Color Classification
│   │
│   ├── Light (white/yellow)
│   │   └── Softwood / birch-type
│   │
│   ├── Reddish
│   │   └── Tropical hardwood (mahogany/narra group)
│   │
│   ├── Dark Brown
│   │   └── Dense hardwood (walnut/teak-type)
│   │
│   └── Multi-tone / Streaked
│       └── Natural hardwood variation
│
├── 6. Surface Texture (Visual Roughness)
│   │
│   ├── Smooth (no visible texture)
│   │   └── Closed-pore wood
│   │
│   └── Rough / Grainy
│       └── Open-pore wood
│
├── 7. Special Figure Detection
│   │
│   ├── Curly / Flame
│   ├── Ribbon
│   ├── Burl
│   │
│   └── → Indicates high-value or specific species group
│
└── 8. Defect / Natural Mark Detection
    │
    ├── Knots
    │   └── Common in softwood (pine-type)
    │
    ├── Dark streaks / gum lines
    │   └── Some hardwood species
    │
    └── Mineral streaks
        └── Natural hardwood indicator
```

---

## OpenCV Capability Map

| Node | Method | Status |
|---|---|---|
| 1. Grain Pattern | Sobel + FFT direction analysis | Planned (11_grain_analysis.py) |
| 2. Pore Visibility | Laplacian + blob detection | Planned (12_pore_detection.py) |
| 3. Growth Ring | HoughCircles + ring contrast | Done (09_circular_mask.py) |
| 4. Ray Pattern | Gabor filter texture orientation | Claude Vision (too subtle for CV) |
| 5. Color | CIEDE2000 pipeline | Done (07, 08) |
| 6. Surface Texture | std_dev + laplacian variance | Done (10_wood_type_classifier.py) |
| 7. Special Figure | FFT + wavelet analysis | Claude Vision (too complex for CV) |
| 8. Defect Detection | Blob + contour anomaly | Planned (13_defect_detection.py) |

---

## Identification Tests

### Test 1: Visual (Primary — always runs)
```
Photo input
  ↓
00: Isolate wood subject
  ↓
10: Wood type classification
    → cross_section  → 09 circular mask → color sample
    → side_cut       → 08 center crop   → color sample
    → flat_cut       → 08 center crop   → color sample
    → painted        → forensic_flag 🚨
    → plywood        → forensic_flag 🚨
    → uncertain      → Claude Vision
  ↓
Tree diagram scoring (1-8)
  ↓
CIEDE2000 match
  ↓
Claude Vision (if low confidence or uncertain)
  ↓
Final visual result
```

### Test 2: Weight Filter (Optional — default null)
```
Selection:
  ○ Hardwood  (heavy, dense — feels heavy to lift)
  ○ Softwood  (light — floats, easy to lift)
  ○ null      (not selected — ignored in scoring)

Effect:
  → eliminates non-matching species from candidate list
  → re-ranks remaining by visual score
  → conflict with visual = forensic flag 🚨
    (visual says Narra/hardwood + weight says softwood = suspicious)
```

### Test 3: Smell Filter (Optional — Expert Mode — default null)
```
Multi-select (any combination):
  □ Aromatic / Fragrant     → Narra, Tindalo, Cedar
  □ Resinous / Piney        → Yakal, Pine
  □ Sweet / Vanilla-like    → Some tropical hardwoods
  □ Bitter / Astringent     → Molave, Ipil
  □ Musty / Earthy          → Old / weathered wood
  □ Odorless                → Processed / Lauan
  □ Spicy / Pepper-like     → Some exotic hardwoods
  □ Sour / Acidic           → Fresh cut green wood
  □ null                    (nothing selected — ignored in scoring)

No gateway — anyone can select
Designed for DENR officers / foresters with field experience
More selections = higher confidence refinement
```

### Combined Scoring Logic
```
visual_score  (always)     weight: 70%
weight_match  (if !null)   weight: 15%
smell_match   (if !null)   weight: 15%

→ weighted average
→ final species match
→ confidence level
→ recommendation
```

---

## Database Design

### Tables

#### wood_species
```sql
id
name                -- "Narra"
local_name          -- "Angsana"
scientific_name     -- "Pterocarpus indicus"
hardness            -- enum: hardwood, softwood
density_min         -- kg/m³ (reference only)
density_max         -- kg/m³
is_protected        -- boolean (DENR protected species)
cites_appendix      -- null, I, II, III
description         -- text
created_at
updated_at
```

#### wood_color_references
```sql
id
species_id          -- FK wood_species
hex                 -- "#c8a96e"
label               -- heartwood, sapwood, aged, fresh_cut
notes               -- "Darker when aged"
created_at
```

#### wood_smell_profiles
```sql
id
species_id          -- FK wood_species
smell               -- enum: aromatic, resinous, sweet, bitter,
                   --        musty, odorless, spicy, sour
intensity           -- enum: faint, moderate, strong
notes               -- "Stronger when freshly cut"
```

#### wood_grain_profiles
```sql
id
species_id          -- FK wood_species (one to one)
grain_pattern       -- enum: straight, wavy, interlocked, irregular
pore_type           -- enum: ring_porous, diffuse_porous, closed
ring_visibility     -- enum: strong, faint
ray_visibility      -- enum: visible, not_visible
surface_texture     -- enum: smooth, rough
special_figure      -- enum: none, curly, ribbon, burl, flame
notes
```

#### wood_reference_images
```sql
id
species_id          -- FK wood_species
image_path          -- storage path
cut_type            -- enum: cross_section, side_cut, flat_cut
label               -- "heartwood cross section"
extracted_hex       -- auto-extracted by pipeline
is_primary          -- boolean
created_at
```

#### wood_scans
```sql
id
user_id             -- FK users
image_path          -- uploaded scan
wood_type_detected  -- enum: cross_section, side_cut, flat_cut,
                   --        painted, plywood, uncertain
weight_input        -- enum: hardwood, softwood, null
smell_input         -- JSON ["aromatic","sweet"] or null
ciede2000_top_match -- species_id FK
ciede2000_delta_e   -- float
vision_top_match    -- species_id FK (Claude Vision result)
final_match         -- species_id FK (combined result)
confidence_score    -- float 0-100
confidence_level    -- enum: very_high, high, medium, low, very_low
forensic_flag       -- boolean
forensic_reason     -- text
recommendation      -- enum: accept, verify, use_ai, retake, flag
location_lat        -- decimal (GPS)
location_lng        -- decimal
created_at
```

#### users
```sql
id
name
email
role                -- enum: student, researcher, officer, admin
organization        -- "DENR Region IV", "UPLB"
created_at
```

### Relationships
```
wood_species
  → wood_color_references    one to many
  → wood_smell_profiles      one to many
  → wood_grain_profiles      one to one
  → wood_reference_images    one to many
  → wood_scans               one to many (via final_match)

users
  → wood_scans               one to many
```

### Key Design Decisions
```
color_references  → many per species (White Oak, Red Oak, Aged Oak)
smell_profiles    → many per species (can have multiple smells)
grain_profiles    → one per species (one definitive grain profile)
reference_images  → many per species, per cut_type
scans             → full audit trail (legal evidence for DENR)
forensic_flag     → on scan level, not species level
weight/smell      → nullable — null = not used in scoring
```






--------------------------------
SELF LEARNING WOOD SCAN CACHE TABLE

wood_scan_cache
├── id
├── -- OpenCV extracted features (text only)
├── wood_type          -- enum: cross_section, side_cut, flat_cut
├── grain_pattern      -- enum: straight, wavy, interlocked, irregular
├── pore_type          -- enum: ring_porous, diffuse_porous, closed
├── surface_texture    -- enum: smooth, rough, coarse
├── ring_visibility    -- enum: strong, faint, none
├── color_hex          -- dominant hex from CIEDE2000
├── hsv_hue            -- int (rounded, not exact)
├── hsv_saturation     -- int (rounded)
├── hsv_value          -- int (rounded)
├── -- Result
├── matched_species_id -- FK wood_species
├── confidence_score   -- float
├── confidence_level   -- enum: very_high, high, medium, low
├── engine_used        -- enum: ciede2000, opencv, both
├── hit_count          -- int default 0 (times this cache was used)
└── created_at

// 1. OpenCV extracts features
$features = extractFeatures($imagePath);

// 2. Check cache first
$cache = WoodScanCache::where([
    'wood_type'       => $features['wood_type'],
    'grain_pattern'   => $features['grain_pattern'],
    'color_hex'       => $features['color_hex'],
    'confidence_level'=> ['very_high', 'high'] // only trust high confidence cache
])->first();

// 3. Cache hit → skip AI Vision
if ($cache) {
    $cache->increment('hit_count');
    return $cache->matched_species_id;
}

// 4. Cache miss → call Claude Vision
$result = callClaudeVision($imagePath);

// 5. Save to cache for next time
WoodScanCache::create([...$features, 'matched_species_id' => $result]);

Key design decisions:
Cache only stores:          Cache does NOT store:
├── Text features ✓         ├── Images ✗
├── Enums ✓                 ├── Raw pixel data ✗
├── Rounded HSV ✓           └── Exact coordinates ✗
└── hit_count ✓
    └── tells you which
        species scanned
        most often! 📊

        Separate sa wood_scans — that's your audit trail. wood_scan_cache is your shortcut table para makatipid ng API calls. 