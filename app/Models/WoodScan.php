<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WoodScan extends Model
{
    protected $fillable = [
        'user_id',
        'image_path',
        'wood_type_detected',
        'weight_input',
        'smell_input',
        'ciede2000_top_match',
        'ciede2000_delta_e',
        'vision_top_match',
        'final_match',
        'confidence_score',
        'confidence_level',
        'forensic_flag',
        'forensic_reason',
        'recommendation',
        'location_lat',
        'location_lng',
        'engine_used',
    ];

    protected $casts = [
        'smell_input'     => 'array',
        'forensic_flag'   => 'boolean',
        'confidence_score' => 'float',
        'ciede2000_delta_e' => 'float',
        'location_lat'    => 'decimal:8',
        'location_lng'    => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ciede2000Match(): BelongsTo
    {
        return $this->belongsTo(WoodSpecies::class, 'ciede2000_top_match');
    }

    public function visionMatch(): BelongsTo
    {
        return $this->belongsTo(WoodSpecies::class, 'vision_top_match');
    }

    public function finalSpecies(): BelongsTo
    {
        return $this->belongsTo(WoodSpecies::class, 'final_match');
    }

    /** Is the result reliable enough to trust without human review? */
    public function isHighConfidence(): bool
    {
        return in_array($this->confidence_level, ['very_high', 'high']);
    }
}
