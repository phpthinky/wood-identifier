<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WoodColorReference extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'species_id',
        'hex',
        'label',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function species(): BelongsTo
    {
        return $this->belongsTo(WoodSpecies::class, 'species_id');
    }
}
