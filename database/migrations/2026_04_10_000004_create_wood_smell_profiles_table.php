<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wood_smell_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('species_id')->constrained('wood_species')->cascadeOnDelete();
            $table->enum('smell', ['aromatic', 'resinous', 'sweet', 'bitter', 'musty', 'odorless', 'spicy', 'sour']);
            $table->enum('intensity', ['faint', 'moderate', 'strong'])->default('moderate');
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wood_smell_profiles');
    }
};
