<?php

namespace App\Services\Wood;

use App\Models\WoodScanCache;
use App\Models\WoodSpecies;

/**
 * Module 6 — Cache Matcher Service
 *
 * Responsibility: Query the wood_scan_cache for a matching feature fingerprint.
 * This is Level 2 of the Brain-First pipeline — the "Memory" layer.
 *
 * Matching strategy:
 *   1. Exact fingerprint match (wood_type + grain_pattern + color_hex)
 *   2. HSV fuzzy match (±15 hue tolerance) if exact fails
 *   3. Only return results with 'high' or 'very_high' confidence
 *
 * Cache HIT  → return species immediately, free & instant, no AI call
 * Cache MISS → return null → caller escalates to AI Evaluator
 *
 * Self-learning: every cache hit increments hit_count for analytics.
 */
class CacheMatcherService
{
    // HSV hue tolerance for fuzzy matching (wood color ≈ ±15° hue)
    private const HUE_TOLERANCE = 15;

    /**
     * Attempt to find a matching species from the cache.
     *
     * @param  array $fingerprint  Output from FeatureExtractorService::extract()
     * @return array|null          Match result with species + confidence, or null on miss
     */
    public function match(array $fingerprint): ?array
    {
        // Try exact match first (fastest)
        $hit = $this->exactMatch($fingerprint);

        // Fall back to HSV fuzzy match
        if ($hit === null) {
            $hit = $this->fuzzyMatch($fingerprint);
        }

        if ($hit === null) {
            return null; // Cache miss — escalate to AI
        }

        // Record the hit for self-learning analytics
        $hit->recordHit();

        $species = $hit->matchedSpecies;

        return [
            'source'          => 'cache',
            'cache_id'        => $hit->id,
            'species_id'      => $hit->matched_species_id,
            'species_name'    => $species?->name,
            'confidence_score' => $hit->confidence_score,
            'confidence_level' => $hit->confidence_level,
            'engine_used'     => $hit->engine_used,
            'hit_count'       => $hit->hit_count,
            'user_verified'   => $hit->user_verified,
        ];
    }

    /**
     * Exact fingerprint match: wood_type + grain_pattern + color_hex
     * Only returns trusted (high/very_high confidence or user-verified) entries.
     */
    private function exactMatch(array $fp): ?WoodScanCache
    {
        return WoodScanCache::trusted()
            ->where('wood_type', $fp['wood_type'])
            ->where('grain_pattern', $fp['grain_pattern'])
            ->where('color_hex', $fp['color_hex'])
            ->orderByDesc('user_verified')   // prefer human-verified entries
            ->orderByDesc('confidence_score')
            ->orderByDesc('hit_count')
            ->first();
    }

    /**
     * HSV fuzzy match: same wood_type + grain + hue within tolerance.
     * Handles slight color variation between scans of the same species.
     */
    private function fuzzyMatch(array $fp): ?WoodScanCache
    {
        $hue = (int)($fp['hsv_hue'] ?? 0);
        $sat = (int)($fp['hsv_saturation'] ?? 0);

        return WoodScanCache::trusted()
            ->where('wood_type', $fp['wood_type'])
            ->where('grain_pattern', $fp['grain_pattern'])
            ->whereBetween('hsv_hue', [
                max(0, $hue - self::HUE_TOLERANCE),
                min(360, $hue + self::HUE_TOLERANCE),
            ])
            ->whereBetween('hsv_saturation', [
                max(0, $sat - 30),
                min(255, $sat + 30),
            ])
            ->orderByDesc('user_verified')
            ->orderByDesc('confidence_score')
            ->orderByDesc('hit_count')
            ->first();
    }

    /**
     * Store a new fingerprint + result in the cache.
     * Called after AI Vision identifies a species on a cache miss.
     */
    public function store(array $fingerprint, int $speciesId, float $confidence, string $level, string $engine): WoodScanCache
    {
        return WoodScanCache::create([
            'wood_type'          => $fingerprint['wood_type'],
            'grain_pattern'      => $fingerprint['grain_pattern'],
            'pore_type'          => $fingerprint['pore_type'],
            'surface_texture'    => $fingerprint['surface_texture'],
            'ring_visibility'    => $fingerprint['ring_visibility'],
            'color_hex'          => $fingerprint['color_hex'],
            'hsv_hue'            => $fingerprint['hsv_hue'],
            'hsv_saturation'     => $fingerprint['hsv_saturation'],
            'hsv_value'          => $fingerprint['hsv_value'],
            'matched_species_id' => $speciesId,
            'confidence_score'   => $confidence,
            'confidence_level'   => $level,
            'engine_used'        => $engine,
            'hit_count'          => 0,
            'user_verified'      => false,
            'created_at'         => now(),
        ]);
    }

    /**
     * Human-in-the-Loop: update a cache entry with a user-verified species.
     * This hardens the cache's confidence over time.
     */
    public function verify(int $cacheId, int $userId, int $confirmedSpeciesId): bool
    {
        $entry = WoodScanCache::find($cacheId);

        if (!$entry) {
            return false;
        }

        $entry->markVerified($userId, $confirmedSpeciesId);
        return true;
    }

    /**
     * Get cache analytics: top-10 most scanned species.
     * Useful for field reporting (DENR officers, researchers).
     */
    public function topScannedSpecies(int $limit = 10): array
    {
        return WoodScanCache::with('matchedSpecies')
            ->whereNotNull('matched_species_id')
            ->selectRaw('matched_species_id, SUM(hit_count) as total_hits')
            ->groupBy('matched_species_id')
            ->orderByDesc('total_hits')
            ->limit($limit)
            ->get()
            ->map(fn($row) => [
                'species_id'   => $row->matched_species_id,
                'species_name' => $row->matchedSpecies?->name,
                'total_hits'   => $row->total_hits,
            ])
            ->toArray();
    }
}
