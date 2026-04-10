<?php

namespace Database\Seeders;

use App\Models\WoodColorReference;
use App\Models\WoodGrainProfile;
use App\Models\WoodSmellProfile;
use App\Models\WoodSpecies;
use Illuminate\Database\Seeder;

/**
 * Module 11 — Wood Species Seeder
 *
 * Seeds the reference database with Philippine and common wood species.
 * Color references match the WOOD_REFERENCES array in 08_full_pipeline.py
 * so the CIEDE2000 pipeline can be validated against known data.
 */
class WoodSpeciesSeeder extends Seeder
{
    public function run(): void
    {
        $species = [
            [
                'species' => [
                    'name'            => 'Narra',
                    'local_name'      => 'Angsana',
                    'scientific_name' => 'Pterocarpus indicus',
                    'hardness'        => 'hardwood',
                    'density_min'     => 560,
                    'density_max'     => 800,
                    'is_protected'    => true,
                    'cites_appendix'  => 'II',
                    'description'     => 'Philippine national tree. Golden-brown hardwood with aromatic scent. Heavily protected by DENR.',
                ],
                'colors' => [
                    ['hex' => '#c8a96e', 'label' => 'heartwood'],
                    ['hex' => '#d4b87a', 'label' => 'fresh_cut'],
                    ['hex' => '#b89558', 'label' => 'aged'],
                    ['hex' => '#e8d090', 'label' => 'sapwood'],
                ],
                'smells' => [
                    ['smell' => 'aromatic', 'intensity' => 'strong'],
                    ['smell' => 'sweet', 'intensity' => 'moderate'],
                ],
                'grain' => [
                    'grain_pattern'  => 'interlocked',
                    'pore_type'      => 'diffuse_porous',
                    'ring_visibility' => 'faint',
                    'ray_visibility'  => 'visible',
                    'surface_texture' => 'smooth',
                    'special_figure'  => 'ribbon',
                ],
            ],
            [
                'species' => [
                    'name'            => 'Mahogany',
                    'local_name'      => 'Mahogany',
                    'scientific_name' => 'Swietenia macrophylla',
                    'hardness'        => 'hardwood',
                    'density_min'     => 500,
                    'density_max'     => 700,
                    'is_protected'    => false,
                    'cites_appendix'  => 'II',
                    'description'     => 'Popular reddish-brown hardwood. Widely used in furniture and flooring.',
                ],
                'colors' => [
                    ['hex' => '#8b4513', 'label' => 'heartwood'],
                    ['hex' => '#a0522d', 'label' => 'fresh_cut'],
                    ['hex' => '#7a3b10', 'label' => 'aged'],
                ],
                'smells' => [
                    ['smell' => 'odorless', 'intensity' => 'moderate'],
                ],
                'grain' => [
                    'grain_pattern'   => 'interlocked',
                    'pore_type'       => 'diffuse_porous',
                    'ring_visibility'  => 'faint',
                    'ray_visibility'   => 'not_visible',
                    'surface_texture'  => 'smooth',
                    'special_figure'   => 'ribbon',
                ],
            ],
            [
                'species' => [
                    'name'            => 'Molave',
                    'local_name'      => 'Tugas',
                    'scientific_name' => 'Vitex parviflora',
                    'hardness'        => 'hardwood',
                    'density_min'     => 700,
                    'density_max'     => 900,
                    'is_protected'    => true,
                    'cites_appendix'  => null,
                    'description'     => 'One of the hardest Philippine hardwoods. Dense, durable, used for bridges and heavy construction.',
                ],
                'colors' => [
                    ['hex' => '#c19a6b', 'label' => 'heartwood'],
                    ['hex' => '#d4aa7a', 'label' => 'fresh_cut'],
                ],
                'smells' => [
                    ['smell' => 'bitter', 'intensity' => 'moderate'],
                ],
                'grain' => [
                    'grain_pattern'   => 'interlocked',
                    'pore_type'       => 'diffuse_porous',
                    'ring_visibility'  => 'faint',
                    'ray_visibility'   => 'visible',
                    'surface_texture'  => 'rough',
                    'special_figure'   => 'none',
                ],
            ],
            [
                'species' => [
                    'name'            => 'Ipil',
                    'local_name'      => 'Ipil',
                    'scientific_name' => 'Intsia bijuga',
                    'hardness'        => 'hardwood',
                    'density_min'     => 800,
                    'density_max'     => 1050,
                    'is_protected'    => true,
                    'cites_appendix'  => null,
                    'description'     => 'Extremely dense Philippine hardwood. Dark brown. Used in flooring and marine applications.',
                ],
                'colors' => [
                    ['hex' => '#7b5e3a', 'label' => 'heartwood'],
                    ['hex' => '#6a4e2e', 'label' => 'aged'],
                ],
                'smells' => [
                    ['smell' => 'bitter', 'intensity' => 'strong'],
                ],
                'grain' => [
                    'grain_pattern'   => 'interlocked',
                    'pore_type'       => 'diffuse_porous',
                    'ring_visibility'  => 'faint',
                    'ray_visibility'   => 'not_visible',
                    'surface_texture'  => 'rough',
                    'special_figure'   => 'none',
                ],
            ],
            [
                'species' => [
                    'name'            => 'Yakal',
                    'local_name'      => 'Yakal',
                    'scientific_name' => 'Shorea astylosa',
                    'hardness'        => 'hardwood',
                    'density_min'     => 700,
                    'density_max'     => 980,
                    'is_protected'    => true,
                    'cites_appendix'  => null,
                    'description'     => 'Dark reddish-brown hardwood. Highly durable, used in structural and marine applications.',
                ],
                'colors' => [
                    ['hex' => '#6b4226', 'label' => 'heartwood'],
                    ['hex' => '#7d4e2e', 'label' => 'fresh_cut'],
                ],
                'smells' => [
                    ['smell' => 'resinous', 'intensity' => 'strong'],
                ],
                'grain' => [
                    'grain_pattern'   => 'interlocked',
                    'pore_type'       => 'diffuse_porous',
                    'ring_visibility'  => 'faint',
                    'ray_visibility'   => 'not_visible',
                    'surface_texture'  => 'rough',
                    'special_figure'   => 'none',
                ],
            ],
            [
                'species' => [
                    'name'            => 'Tindalo',
                    'local_name'      => 'Tindalo',
                    'scientific_name' => 'Afzelia rhomboidea',
                    'hardness'        => 'hardwood',
                    'density_min'     => 750,
                    'density_max'     => 950,
                    'is_protected'    => true,
                    'cites_appendix'  => null,
                    'description'     => 'Reddish-brown heartwood with distinctive aromatic scent. Premium Philippine hardwood.',
                ],
                'colors' => [
                    ['hex' => '#b5651d', 'label' => 'heartwood'],
                    ['hex' => '#c47520', 'label' => 'fresh_cut'],
                    ['hex' => '#a05010', 'label' => 'aged'],
                ],
                'smells' => [
                    ['smell' => 'aromatic', 'intensity' => 'moderate'],
                ],
                'grain' => [
                    'grain_pattern'   => 'interlocked',
                    'pore_type'       => 'diffuse_porous',
                    'ring_visibility'  => 'faint',
                    'ray_visibility'   => 'visible',
                    'surface_texture'  => 'smooth',
                    'special_figure'   => 'none',
                ],
            ],
            [
                'species' => [
                    'name'            => 'Kamagong',
                    'local_name'      => 'Kamagong / Mabolo',
                    'scientific_name' => 'Diospyros blancoi',
                    'hardness'        => 'hardwood',
                    'density_min'     => 900,
                    'density_max'     => 1200,
                    'is_protected'    => true,
                    'cites_appendix'  => null,
                    'description'     => 'Philippine ebony. Very dark heartwood, almost black. Extremely dense and valued.',
                ],
                'colors' => [
                    ['hex' => '#2b1a0e', 'label' => 'heartwood'],
                    ['hex' => '#1a0f05', 'label' => 'aged'],
                    ['hex' => '#5a3822', 'label' => 'sapwood'],
                ],
                'smells' => [
                    ['smell' => 'odorless', 'intensity' => 'faint'],
                ],
                'grain' => [
                    'grain_pattern'   => 'straight',
                    'pore_type'       => 'diffuse_porous',
                    'ring_visibility'  => 'faint',
                    'ray_visibility'   => 'not_visible',
                    'surface_texture'  => 'smooth',
                    'special_figure'   => 'none',
                ],
            ],
            [
                'species' => [
                    'name'            => 'Lauan',
                    'local_name'      => 'Philippine Mahogany',
                    'scientific_name' => 'Shorea sp.',
                    'hardness'        => 'hardwood',
                    'density_min'     => 400,
                    'density_max'     => 600,
                    'is_protected'    => false,
                    'cites_appendix'  => null,
                    'description'     => 'Light pink to pale brown. Common Philippine export timber. Often used as plywood veneer.',
                ],
                'colors' => [
                    ['hex' => '#d4a574', 'label' => 'heartwood'],
                    ['hex' => '#e0b888', 'label' => 'sapwood'],
                    ['hex' => '#c09060', 'label' => 'aged'],
                ],
                'smells' => [
                    ['smell' => 'odorless', 'intensity' => 'faint'],
                ],
                'grain' => [
                    'grain_pattern'   => 'interlocked',
                    'pore_type'       => 'diffuse_porous',
                    'ring_visibility'  => 'none',
                    'ray_visibility'   => 'not_visible',
                    'surface_texture'  => 'smooth',
                    'special_figure'   => 'ribbon',
                ],
            ],
            [
                'species' => [
                    'name'            => 'Oak',
                    'local_name'      => 'Oak',
                    'scientific_name' => 'Quercus sp.',
                    'hardness'        => 'hardwood',
                    'density_min'     => 600,
                    'density_max'     => 900,
                    'is_protected'    => false,
                    'cites_appendix'  => null,
                    'description'     => 'Common hardwood. Multiple varieties (Red, White). Strong, ring-porous grain.',
                ],
                'colors' => [
                    ['hex' => '#c8a87a', 'label' => 'heartwood'],   // Red Oak
                    ['hex' => '#f0dfc0', 'label' => 'sapwood'],     // White Oak
                    ['hex' => '#b8864e', 'label' => 'aged'],        // Aged Oak
                ],
                'smells' => [
                    ['smell' => 'musty', 'intensity' => 'faint'],
                ],
                'grain' => [
                    'grain_pattern'   => 'straight',
                    'pore_type'       => 'ring_porous',
                    'ring_visibility'  => 'strong',
                    'ray_visibility'   => 'visible',
                    'surface_texture'  => 'rough',
                    'special_figure'   => 'none',
                ],
            ],
        ];

        foreach ($species as $data) {
            $sp = WoodSpecies::create($data['species']);

            foreach ($data['colors'] as $color) {
                WoodColorReference::create(array_merge(['species_id' => $sp->id], $color));
            }

            foreach ($data['smells'] as $smell) {
                WoodSmellProfile::create(array_merge(['species_id' => $sp->id], $smell));
            }

            WoodGrainProfile::create(array_merge(['species_id' => $sp->id], $data['grain']));
        }

        $this->command->info('Wood species seeded: ' . count($species) . ' species with colors, smells, and grain profiles.');
    }
}
