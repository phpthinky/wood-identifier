<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wood_color_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('species_id')->constrained('wood_species')->cascadeOnDelete();
            $table->string('hex', 10);                     // "#c8a96e"
            $table->enum('label', ['heartwood', 'sapwood', 'aged', 'fresh_cut'])->default('heartwood');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wood_color_references');
    }
};
