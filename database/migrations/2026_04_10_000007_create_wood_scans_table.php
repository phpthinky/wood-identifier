<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wood_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('image_path');

            // OpenCV detection
            $table->enum('wood_type_detected', [
                'cross_section', 'side_cut', 'flat_cut', 'painted', 'plywood', 'uncertain'
            ])->nullable();

            // Optional filters
            $table->enum('weight_input', ['hardwood', 'softwood'])->nullable();
            $table->json('smell_input')->nullable(); // ["aromatic","sweet"]

            // CIEDE2000 result
            $table->foreignId('ciede2000_top_match')->nullable()->constrained('wood_species')->nullOnDelete();
            $table->float('ciede2000_delta_e')->nullable();

            // AI Vision result
            $table->foreignId('vision_top_match')->nullable()->constrained('wood_species')->nullOnDelete();

            // Combined final result
            $table->foreignId('final_match')->nullable()->constrained('wood_species')->nullOnDelete();
            $table->float('confidence_score')->nullable();
            $table->enum('confidence_level', ['very_high', 'high', 'medium', 'low', 'very_low'])->nullable();

            // Forensics
            $table->boolean('forensic_flag')->default(false);
            $table->text('forensic_reason')->nullable();
            $table->enum('recommendation', ['accept', 'verify', 'use_ai', 'retake', 'flag'])->nullable();

            // GPS
            $table->decimal('location_lat', 10, 8)->nullable();
            $table->decimal('location_lng', 11, 8)->nullable();

            // Engine used for final result
            $table->enum('engine_used', ['ciede2000', 'opencv', 'ai_vision', 'combined'])->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wood_scans');
    }
};
