<?php

namespace App\Services\Wood;

use App\Models\WoodSpecies;
use RuntimeException;

/**
 * Module 5 — Feature Extractor Service
 *
 * Responsibility: Bridge between Laravel and the OpenCV Python scripts.
 * Runs the modular pipeline scripts (00–10) in the correct order,
 * collects JSON output, and returns a unified feature fingerprint array.
 *
 * Pipeline order (from README):
 *   00_isolate_wood.py          ← isolate subject
 *   10_wood_type_classifier.py  ← detect cut type
 *   09_circular_mask.py         ← if cross_section
 *   08_full_pipeline.py         ← if side_cut / flat_cut
 *
 * All scripts output JSON to stdout only. Errors come as {"error":"..."}.
 */
class FeatureExtractorService
{
    private string $scriptsPath;
    private string $python;

    public function __construct()
    {
        $this->scriptsPath = base_path('scripts');
        $this->python      = config('wood.python_binary', 'python3');
    }

    /**
     * Run the full OpenCV pipeline on an image.
     * Returns a unified feature fingerprint ready for the CacheMatcher.
     *
     * @throws RuntimeException if a script fails fatally
     */
    public function extract(string $absoluteImagePath): array
    {
        // Step 1: Isolate wood subject from background
        $isolation = $this->runScript('00_isolate_wood.py', $absoluteImagePath);

        // Step 2: Detect wood surface type
        $classifier = $this->runScript('10_wood_type_classifier.py', $absoluteImagePath);
        $woodType   = $classifier['classification']['wood_type'] ?? 'uncertain';

        // Step 3: Color analysis — route based on cut type
        $colorData = match ($woodType) {
            'cross_section' => $this->runScript('09_circular_mask.py', $absoluteImagePath),
            default         => $this->runScript('08_full_pipeline.py', $absoluteImagePath),
        };

        // Step 4: Build unified fingerprint from all outputs
        return $this->buildFingerprint($woodType, $classifier, $colorData);
    }

    /**
     * Run a single OpenCV script and parse its JSON stdout.
     *
     * @throws RuntimeException if the script returns an error
     */
    public function runScript(string $script, string $imagePath, array $extraArgs = []): array
    {
        $scriptPath = escapeshellarg("{$this->scriptsPath}/{$script}");
        $imagePath  = escapeshellarg($imagePath);

        $extraStr = '';
        foreach ($extraArgs as $arg) {
            $extraStr .= ' ' . escapeshellarg((string)$arg);
        }

        $command = "{$this->python} {$scriptPath} {$imagePath}{$extraStr} 2>/dev/null";
        $output  = shell_exec($command);

        if ($output === null || trim($output) === '') {
            throw new RuntimeException("Script {$script} produced no output. Check Python/OpenCV installation.");
        }

        $result = json_decode(trim($output), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Script {$script} returned invalid JSON: " . substr($output, 0, 200));
        }

        if (isset($result['error'])) {
            throw new RuntimeException("Script {$script} error: {$result['error']}");
        }

        return $result;
    }

    /**
     * Build the unified feature fingerprint from all script outputs.
     * This is the "fingerprint" stored in wood_scan_cache.
     */
    private function buildFingerprint(string $woodType, array $classifier, array $colorData): array
    {
        $classification = $classifier['classification'] ?? [];
        $texture        = $classifier['texture'] ?? [];
        $directionality = $classifier['directionality'] ?? [];

        // Extract dominant color
        $dominantColor  = $this->extractDominantColor($colorData, $woodType);
        $hsv            = $this->hexToHsv($dominantColor);

        // Determine grain from directionality
        $grainPattern = $this->inferGrainPattern($directionality, $woodType);

        // Determine pore type from texture
        $poreType = $this->inferPoreType($texture);

        // Determine ring visibility
        $ringVisibility = $this->inferRingVisibility($colorData, $woodType);

        // Surface texture
        $surfaceTexture = ($texture['std_dev'] ?? 0) > 20 ? 'rough' : 'smooth';

        // Confidence from CIEDE2000 result
        $confidence      = $colorData['top_match']['confidence'] ?? 'low';
        $deltaE          = $colorData['top_match']['delta_e'] ?? 99.0;
        $confidenceScore = $this->deltaEToScore($deltaE);

        // Top species match from CIEDE2000
        $topSpeciesName = $colorData['top_match']['species'] ?? null;
        $topSpeciesId   = $topSpeciesName ? $this->resolveSpeciesId($topSpeciesName) : null;

        return [
            // Feature fingerprint (for cache matching)
            'wood_type'        => $woodType,
            'grain_pattern'    => $grainPattern,
            'pore_type'        => $poreType,
            'surface_texture'  => $surfaceTexture,
            'ring_visibility'  => $ringVisibility,
            'color_hex'        => $dominantColor,
            'hsv_hue'          => $hsv['h'],
            'hsv_saturation'   => $hsv['s'],
            'hsv_value'        => $hsv['v'],

            // CIEDE2000 result
            'ciede2000_species_id' => $topSpeciesId,
            'ciede2000_species'    => $topSpeciesName,
            'ciede2000_delta_e'    => round((float)$deltaE, 4),
            'confidence_level'     => $confidence,
            'confidence_score'     => round($confidenceScore, 2),
            'recommendation'       => $colorData['recommendation'] ?? 'use_ai',

            // Forensics
            'forensic_flag'   => (bool)($classification['forensic_flag'] ?? false),
            'forensic_reason' => $classification['advice'] ?? null,

            // Photo quality
            'photo_quality' => $colorData['quality']['quality'] ?? 'unknown',
            'photo_advice'  => $colorData['quality']['message'] ?? null,

            // Raw outputs (for audit trail)
            '_classifier_raw' => $classification,
            '_color_raw'      => $colorData['top_3'] ?? [],
        ];
    }

    /** Extract dominant hex from either pipeline script output */
    private function extractDominantColor(array $colorData, string $woodType): string
    {
        if ($woodType === 'cross_section') {
            // 09_circular_mask.py: dominant_colors[0]['hex']
            return $colorData['dominant_colors'][0]['hex'] ?? '#808080';
        }

        // 08_full_pipeline.py: sample.hex
        return $colorData['sample']['hex'] ?? '#808080';
    }

    /** Hex to rounded HSV values (for cache fingerprint) */
    private function hexToHsv(string $hex): array
    {
        $hex = ltrim($hex, '#');
        $r   = hexdec(substr($hex, 0, 2)) / 255;
        $g   = hexdec(substr($hex, 2, 2)) / 255;
        $b   = hexdec(substr($hex, 4, 2)) / 255;

        $max   = max($r, $g, $b);
        $min   = min($r, $g, $b);
        $delta = $max - $min;

        $v = $max;
        $s = $max == 0 ? 0 : $delta / $max;

        if ($delta == 0) {
            $h = 0;
        } elseif ($max == $r) {
            $h = 60 * fmod(($g - $b) / $delta, 6);
        } elseif ($max == $g) {
            $h = 60 * (($b - $r) / $delta + 2);
        } else {
            $h = 60 * (($r - $g) / $delta + 4);
        }

        if ($h < 0) {
            $h += 360;
        }

        return [
            'h' => (int)round($h),
            's' => (int)round($s * 255),
            'v' => (int)round($v * 255),
        ];
    }

    /** Map CIEDE2000 delta_e to a 0–100 confidence score */
    private function deltaEToScore(float $deltaE): float
    {
        if ($deltaE <= 1)  return 99.0;
        if ($deltaE <= 2)  return 95.0;
        if ($deltaE <= 5)  return 80.0;
        if ($deltaE <= 10) return 60.0;
        if ($deltaE <= 20) return 35.0;
        return max(0, 100 - $deltaE * 2);
    }

    /** Infer grain pattern from Sobel directionality analysis */
    private function inferGrainPattern(array $directionality, string $woodType): string
    {
        if ($woodType === 'cross_section') {
            return 'straight'; // Annual rings = concentric, treated as straight
        }

        $directional = $directionality['directional'] ?? false;
        $ratio       = $directionality['ratio_x_to_y'] ?? 1.0;

        if (!$directional) {
            return 'irregular';
        }

        if ($ratio > 3.0 || $ratio < 0.3) {
            return 'straight'; // Very directional = straight grain
        }

        return 'wavy'; // Moderately directional = wavy
    }

    /** Infer pore type from texture analysis */
    private function inferPoreType(array $texture): string
    {
        $laplacian = $texture['laplacian_var'] ?? 0;

        if ($laplacian > 300) return 'ring_porous';   // High variance = visible pores in lines
        if ($laplacian > 100) return 'diffuse_porous'; // Medium variance = spread pores
        return 'closed';                               // Low variance = no visible pores
    }

    /** Infer ring visibility from the classifier/circular_mask output */
    private function inferRingVisibility(array $colorData, string $woodType): string
    {
        if ($woodType === 'cross_section' && isset($colorData['inner_radius'])) {
            return 'strong';
        }

        $quality = $colorData['quality']['quality'] ?? 'unknown';
        if ($quality === 'good') return 'faint';
        return 'none';
    }

    /** Resolve species name to DB id (best-effort, nullable) */
    private function resolveSpeciesId(string $name): ?int
    {
        $species = WoodSpecies::where('name', 'like', "%{$name}%")->first();
        return $species?->id;
    }
}
