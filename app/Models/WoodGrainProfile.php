<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WoodGrainProfile extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'species_id',
        'grain_pattern',
        'pore_type',
        'ring_visibility',
        'ray_visibility',
        'surface_texture',
        'special_figure',
        'notes',
    ];

    public function species(): BelongsTo
    {
        return $this->belongsTo(WoodSpecies::class, 'species_id');
    }
}
