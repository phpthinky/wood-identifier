<?php

namespace App\Services\Wood;

use App\Models\WoodScan;
use App\Models\WoodSpecies;

/**
 * Module 4 — Wood Brain Service (Singleton Orchestrator)
 *
 * This is the CORE of the entire system. Registered as a Singleton
 * in WoodServiceProvider so the same instance is reused throughout
 * the request lifecycle (no repeated DB/config reads).
 *
 * The "Logic Gate" — Brain-First pipeline:
 * ─────────────────────────────────────────
 * Level 1 │ LOCAL BRAIN   │ OpenCV Python scripts (FeatureExtractor)
 * Level 2 │ MEMORY        │ wood_scan_cache SQL lookup (CacheMatcher)
 * Level 3 │ AI FALLBACK   │ Claude Vision via Prism (AiEvaluator)
 * ─────────────────────────────────────────
 *
 * Level 3 only fires if Level 1 + Level 2 cannot produce a
 * high-confidence result. This keeps costs at zero for common cases.
 *
 * Combined scoring (from README):
 *   visual_score  70%  (always — CIEDE2000 or AI Vision)
 *   weight_match  15%  (optional — hardwood/softwood)
 *   smell_match   15%  (optional — multi-select expert input)
 */
class WoodBrainService
{
    public function __construct(
        private readonly FeatureExtractorService $featureExtractor,
        private readonly CacheMatcherService     $cacheMatcher,
        private readonly AiEvaluatorService      $aiEvaluator,
    ) {}

    /**
     * Run the full Brain-First identification pipeline on an image.
     *
     * @param  string   $imagePath    Absolute path (from ImageDecoderService)
     * @param  int|null $userId       Authenticated user ID for audit trail
     * @param  array    $options      ['weight' => 'hardwood', 'smell' => ['aromatic'], 'lat' => ..., 'lng' => ...]
     * @return array                  Full scan result
     */
    public function identify(string $imagePath, ?int $userId = null, array $options = []): array
    {
        // ──────────────────────────────────────────────
        // LEVEL 1: LOCAL BRAIN — OpenCV Feature Extraction
        // ──────────────────────────────────────────────
        try {
            $fingerprint = $this->featureExtractor->extract($imagePath);
        } catch (\Throwable $e) {
            return $this->errorResponse('opencv_failure', $e->getMessage());
        }

        // If OpenCV itself says retake (bad photo), stop here
        if (($fingerprint['recommendation'] ?? '') === 'retake') {
            return $this->buildResponse(
                fingerprint: $fingerprint,
                source: 'opencv',
                speciesId: null,
                speciesName: null,
                confidence: 0,
                level: 'very_low',
                recommendation: 'retake',
                options: $options
            );
        }

        // Forensic short-circuit: painted or plywood → flag immediately
        if ($fingerprint['forensic_flag'] ?? false) {
            return $this->buildResponse(
                fingerprint: $fingerprint,
                source: 'opencv',
                speciesId: null,
                speciesName: null,
                confidence: 0,
                level: 'very_low',
                recommendation: 'flag',
                options: $options
            );
        }

        // ──────────────────────────────────────────────
        // LEVEL 2: MEMORY — Cache Lookup
        // ──────────────────────────────────────────────
        $cacheHit = $this->cacheMatcher->match($fingerprint);

        if ($cacheHit !== null) {
            // Cache HIT — instant, free, no AI needed
            $result = $this->buildResponse(
                fingerprint: $fingerprint,
                source: 'cache',
                speciesId: $cacheHit['species_id'],
                speciesName: $cacheHit['species_name'],
                confidence: $cacheHit['confidence_score'],
                level: $cacheHit['confidence_level'],
                recommendation: 'accept',
                options: $options,
                extra: ['cache_id' => $cacheHit['cache_id'], 'cache_hit_count' => $cacheHit['hit_count']]
            );

            $this->saveAuditTrail($result, $fingerprint, $imagePath, $userId, $options);
            return $result;
        }

        // ──────────────────────────────────────────────
        // LEVEL 3: AI FALLBACK — Claude Vision via Prism
        // Only triggers here — after local brain + cache both fail.
        // ──────────────────────────────────────────────
        $aiResult = $this->aiEvaluator->evaluate($imagePath, $fingerprint);

        // Store AI result in cache for next time (self-learning)
        if ($aiResult['success'] && $aiResult['species_id']) {
            $this->cacheMatcher->store(
                fingerprint: $fingerprint,
                speciesId: $aiResult['species_id'],
                confidence: $aiResult['confidence_score'],
                level: $aiResult['confidence_level'],
                engine: 'ai_vision'
            );
        }

        $result = $this->buildResponse(
            fingerprint: $fingerprint,
            source: 'ai_vision',
            speciesId: $aiResult['species_id'],
            speciesName: $aiResult['species_name'],
            confidence: $aiResult['confidence_score'],
            level: $aiResult['confidence_level'],
            recommendation: $aiResult['recommendation'],
            options: $options,
            extra: [
                'ai_provider'     => $aiResult['ai_provider'],
                'ai_forensic_flag' => $aiResult['forensic_flag'],
                'ai_forensic_reason' => $aiResult['forensic_reason'],
                'matches_opencv'  => $aiResult['matches_opencv'] ?? true,
            ]
        );

        $this->saveAuditTrail($result, $fingerprint, $imagePath, $userId, $options, $aiResult);
        return $result;
    }

    /**
     * Human-in-the-Loop: user confirms or corrects the scan result.
     * Updates the cache entry to harden confidence for future scans.
     */
    public function verifyResult(int $scanId, int $userId, int $confirmedSpeciesId): array
    {
        $scan = WoodScan::find($scanId);

        if (!$scan) {
            return ['success' => false, 'error' => 'Scan not found'];
        }

        // Update the scan's final result
        $scan->update([
            'final_match'      => $confirmedSpeciesId,
            'confidence_level' => 'very_high',
            'confidence_score' => 95.0,
        ]);

        // Harden the cache — next scan of the same wood type won't need AI
        // Find the most recent cache entry related to this scan's fingerprint
        $cacheEntry = \App\Models\WoodScanCache::where('wood_type', $scan->wood_type_detected)
            ->whereNotNull('matched_species_id')
            ->orderByDesc('created_at')
            ->first();

        if ($cacheEntry) {
            $this->cacheMatcher->verify($cacheEntry->id, $userId, $confirmedSpeciesId);
        }

        $species = WoodSpecies::find($confirmedSpeciesId);

        return [
            'success'      => true,
            'scan_id'      => $scanId,
            'species_id'   => $confirmedSpeciesId,
            'species_name' => $species?->name,
            'message'      => 'Result verified. Cache hardened for future scans.',
        ];
    }

    /**
     * Apply optional weight + smell filters to re-rank the result.
     * These adjust the confidence score but do not change the species.
     *
     * Scoring weights (from README):
     *   visual_score  70%
     *   weight_match  15%
     *   smell_match   15%
     */
    private function applyFilters(array $result, array $options, ?int $speciesId): array
    {
        $weight     = $options['weight'] ?? null;
        $smellInput = $options['smell'] ?? [];

        $visualScore  = $result['confidence_score'];
        $weightScore  = null;
        $smellScore   = null;
        $forensicFlag = $result['forensic_flag'] ?? false;

        if ($speciesId && $weight) {
            $species     = WoodSpecies::find($speciesId);
            $weightMatch = $species && $species->hardness === $weight;
            $weightScore = $weightMatch ? 100.0 : 0.0;

            // Conflict: visual says hardwood, weight says softwood → forensic
            if (!$weightMatch) {
                $forensicFlag = true;
                $result['forensic_reason'] = "Weight conflict: visual suggests {$species?->hardness}, weight input says {$weight}.";
            }
        }

        if ($speciesId && !empty($smellInput)) {
            $smellProfiles = \App\Models\WoodSmellProfile::where('species_id', $speciesId)
                ->pluck('smell')
                ->toArray();

            $matches    = count(array_intersect($smellInput, $smellProfiles));
            $smellScore = $matches > 0 ? ($matches / count($smellInput)) * 100 : 0;
        }

        // Calculate weighted final score
        $finalScore = $visualScore * 0.70;
        if ($weightScore !== null) $finalScore += $weightScore * 0.15;
        if ($smellScore  !== null) $finalScore += $smellScore  * 0.15;

        $result['confidence_score'] = round($finalScore, 2);
        $result['confidence_level'] = $this->scoreToLevel($finalScore);
        $result['forensic_flag']    = $forensicFlag;
        $result['weight_applied']   = $weight;
        $result['smell_applied']    = $smellInput ?: null;

        return $result;
    }

    /** Build the standardized scan result array */
    private function buildResponse(
        array   $fingerprint,
        string  $source,
        ?int    $speciesId,
        ?string $speciesName,
        float   $confidence,
        string  $level,
        string  $recommendation,
        array   $options = [],
        array   $extra = []
    ): array {
        $result = [
            'success'          => true,
            'source'           => $source,
            'species_id'       => $speciesId,
            'species_name'     => $speciesName,
            'confidence_score' => round($confidence, 2),
            'confidence_level' => $level,
            'recommendation'   => $recommendation,
            'forensic_flag'    => $fingerprint['forensic_flag'] ?? false,
            'forensic_reason'  => $fingerprint['forensic_reason'] ?? null,

            // OpenCV features (educational — explain WHY this is the result)
            'wood_type'        => $fingerprint['wood_type'],
            'grain_pattern'    => $fingerprint['grain_pattern'],
            'pore_type'        => $fingerprint['pore_type'],
            'color_hex'        => $fingerprint['color_hex'],
            'surface_texture'  => $fingerprint['surface_texture'],
            'photo_quality'    => $fingerprint['photo_quality'],
            'photo_advice'     => $fingerprint['photo_advice'],

            // CIEDE2000 local result (always available for transparency)
            'ciede2000_species'  => $fingerprint['ciede2000_species'] ?? null,
            'ciede2000_delta_e'  => $fingerprint['ciede2000_delta_e'] ?? null,
            'ciede2000_top3'     => $fingerprint['_color_raw'] ?? [],
        ];

        // Merge any extra source-specific data
        $result = array_merge($result, $extra);

        // Apply optional weight/smell filters
        $result = $this->applyFilters($result, $options, $speciesId);

        return $result;
    }

    /** Save to wood_scans audit trail */
    private function saveAuditTrail(
        array   $result,
        array   $fingerprint,
        string  $imagePath,
        ?int    $userId,
        array   $options,
        array   $aiResult = []
    ): WoodScan {
        return WoodScan::create([
            'user_id'             => $userId,
            'image_path'          => $imagePath,
            'wood_type_detected'  => $fingerprint['wood_type'],
            'weight_input'        => $options['weight'] ?? null,
            'smell_input'         => !empty($options['smell']) ? $options['smell'] : null,
            'ciede2000_top_match' => $fingerprint['ciede2000_species_id'] ?? null,
            'ciede2000_delta_e'   => $fingerprint['ciede2000_delta_e'] ?? null,
            'vision_top_match'    => $aiResult['species_id'] ?? null,
            'final_match'         => $result['species_id'],
            'confidence_score'    => $result['confidence_score'],
            'confidence_level'    => $result['confidence_level'],
            'forensic_flag'       => $result['forensic_flag'],
            'forensic_reason'     => $result['forensic_reason'],
            'recommendation'      => $result['recommendation'],
            'location_lat'        => $options['lat'] ?? null,
            'location_lng'        => $options['lng'] ?? null,
            'engine_used'         => $this->engineLabel($result['source']),
        ]);
    }

    private function engineLabel(string $source): string
    {
        return match ($source) {
            'cache'    => 'ciede2000',
            'ai_vision' => 'ai_vision',
            default    => 'opencv',
        };
    }

    private function scoreToLevel(float $score): string
    {
        if ($score >= 90) return 'very_high';
        if ($score >= 70) return 'high';
        if ($score >= 50) return 'medium';
        if ($score >= 30) return 'low';
        return 'very_low';
    }

    private function errorResponse(string $type, string $message): array
    {
        return [
            'success'          => false,
            'error_type'       => $type,
            'error'            => $message,
            'species_id'       => null,
            'species_name'     => null,
            'confidence_score' => 0,
            'confidence_level' => 'very_low',
            'recommendation'   => 'retake',
            'forensic_flag'    => false,
        ];
    }
}
