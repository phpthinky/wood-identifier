<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WoodSmellProfile extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'species_id',
        'smell',
        'intensity',
        'notes',
    ];

    public function species(): BelongsTo
    {
        return $this->belongsTo(WoodSpecies::class, 'species_id');
    }
}
