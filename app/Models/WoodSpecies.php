<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WoodSpecies extends Model
{
    protected $fillable = [
        'name',
        'local_name',
        'scientific_name',
        'hardness',
        'density_min',
        'density_max',
        'is_protected',
        'cites_appendix',
        'description',
    ];

    protected $casts = [
        'is_protected' => 'boolean',
        'density_min'  => 'float',
        'density_max'  => 'float',
    ];

    public function colorReferences(): HasMany
    {
        return $this->hasMany(WoodColorReference::class, 'species_id');
    }

    public function smellProfiles(): HasMany
    {
        return $this->hasMany(WoodSmellProfile::class, 'species_id');
    }

    public function grainProfile(): HasOne
    {
        return $this->hasOne(WoodGrainProfile::class, 'species_id');
    }

    public function referenceImages(): HasMany
    {
        return $this->hasMany(WoodReferenceImage::class, 'species_id');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(WoodScan::class, 'final_match');
    }

    /** Return all hex references as a simple array for CIEDE2000 pipeline */
    public function getColorRefsForPipeline(): array
    {
        return $this->colorReferences->map(fn($c) => [
            'species' => $this->name,
            'species_id' => $this->id,
            'hex' => $c->hex,
            'label' => $c->label,
        ])->toArray();
    }
}
