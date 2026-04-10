<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WoodScanCache extends Model
{
    public $timestamps = false;

    protected $table = 'wood_scan_cache';

    protected $fillable = [
        'wood_type',
        'grain_pattern',
        'pore_type',
        'surface_texture',
        'ring_visibility',
        'color_hex',
        'hsv_hue',
        'hsv_saturation',
        'hsv_value',
        'matched_species_id',
        'confidence_score',
        'confidence_level',
        'engine_used',
        'hit_count',
        'user_verified',
        'verified_by',
        'verified_at',
        'created_at',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'hit_count'        => 'integer',
        'user_verified'    => 'boolean',
        'verified_at'      => 'datetime',
        'created_at'       => 'datetime',
    ];

    public function matchedSpecies(): BelongsTo
    {
        return $this->belongsTo(WoodSpecies::class, 'matched_species_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** Increment the cache hit counter (self-learning reinforcement) */
    public function recordHit(): void
    {
        $this->increment('hit_count');
    }

    /**
     * Mark this cache entry as human-verified.
     * This is the Human-in-the-Loop reinforcement step.
     */
    public function markVerified(int $userId, int $confirmedSpeciesId): void
    {
        $this->update([
            'user_verified'      => true,
            'verified_by'        => $userId,
            'verified_at'        => now(),
            'matched_species_id' => $confirmedSpeciesId,
            'confidence_level'   => 'very_high', // trust bumped after human confirmation
            'confidence_score'   => 95.0,
        ]);
    }

    /** Only return trusted cache entries (high confidence or human-verified) */
    public function scopeTrusted($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('confidence_level', ['very_high', 'high'])
              ->orWhere('user_verified', true);
        });
    }
}
