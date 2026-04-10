<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wood_grain_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('species_id')->unique()->constrained('wood_species')->cascadeOnDelete();
            $table->enum('grain_pattern', ['straight', 'wavy', 'interlocked', 'irregular'])->nullable();
            $table->enum('pore_type', ['ring_porous', 'diffuse_porous', 'closed'])->nullable();
            $table->enum('ring_visibility', ['strong', 'faint', 'none'])->nullable();
            $table->enum('ray_visibility', ['visible', 'not_visible'])->nullable();
            $table->enum('surface_texture', ['smooth', 'rough'])->nullable();
            $table->enum('special_figure', ['none', 'curly', 'ribbon', 'burl', 'flame'])->default('none');
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wood_grain_profiles');
    }
};
