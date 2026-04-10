<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wood_reference_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('species_id')->constrained('wood_species')->cascadeOnDelete();
            $table->string('image_path');
            $table->enum('cut_type', ['cross_section', 'side_cut', 'flat_cut']);
            $table->string('label')->nullable();             // "heartwood cross section"
            $table->string('extracted_hex', 10)->nullable(); // auto-extracted by pipeline
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wood_reference_images');
    }
};
