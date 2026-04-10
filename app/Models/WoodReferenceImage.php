<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WoodReferenceImage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'species_id',
        'image_path',
        'cut_type',
        'label',
        'extracted_hex',
        'is_primary',
    ];

    protected $casts = [
        'is_primary'  => 'boolean',
        'created_at'  => 'datetime',
    ];

    public function species(): BelongsTo
    {
        return $this->belongsTo(WoodSpecies::class, 'species_id');
    }
}
