<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wood Scan Cache — The Self-Learning Shortcut Table.
     *
     * Stores extracted feature fingerprints + their verified species matches.
     * On cache HIT (high-confidence): skip AI Vision call entirely (free + instant).
     * On cache MISS: trigger AI Vision, then store result here for next time.
     * hit_count reveals the most commonly scanned species in the field.
     */
    public function up(): void
    {
        Schema::create('wood_scan_cache', function (Blueprint $table) {
            $table->id();

            // --- OpenCV extracted features (text fingerprint) ---
            $table->enum('wood_type', ['cross_section', 'side_cut', 'flat_cut', 'painted', 'plywood', 'uncertain'])->nullable();
            $table->enum('grain_pattern', ['straight', 'wavy', 'interlocked', 'irregular'])->nullable();
            $table->enum('pore_type', ['ring_porous', 'diffuse_porous', 'closed'])->nullable();
            $table->enum('surface_texture', ['smooth', 'rough', 'coarse'])->nullable();
            $table->enum('ring_visibility', ['strong', 'faint', 'none'])->nullable();

            // Color fingerprint (rounded for fuzzy matching)
            $table->string('color_hex', 10)->nullable();   // dominant hex from CIEDE2000
            $table->unsignedSmallInteger('hsv_hue')->nullable();        // rounded 0-360
            $table->unsignedSmallInteger('hsv_saturation')->nullable(); // rounded 0-255
            $table->unsignedSmallInteger('hsv_value')->nullable();      // rounded 0-255

            // --- Result ---
            $table->foreignId('matched_species_id')->nullable()->constrained('wood_species')->nullOnDelete();
            $table->float('confidence_score')->default(0);
            $table->enum('confidence_level', ['very_high', 'high', 'medium', 'low'])->default('low');
            $table->enum('engine_used', ['ciede2000', 'opencv', 'ai_vision', 'combined'])->default('ciede2000');

            // Self-learning counter — tells us which species is scanned most often
            $table->unsignedInteger('hit_count')->default(0);

            // Human-in-the-loop: was this verified by a user?
            $table->boolean('user_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamp('created_at')->nullable();

            // Index for fast fingerprint lookup
            $table->index(['wood_type', 'grain_pattern', 'color_hex', 'confidence_level'], 'idx_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wood_scan_cache');
    }
};
