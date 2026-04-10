<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wood_species', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // "Narra"
            $table->string('local_name')->nullable();      // "Angsana"
            $table->string('scientific_name')->nullable(); // "Pterocarpus indicus"
            $table->enum('hardness', ['hardwood', 'softwood']);
            $table->float('density_min')->nullable();      // kg/m³
            $table->float('density_max')->nullable();      // kg/m³
            $table->boolean('is_protected')->default(false);
            $table->string('cites_appendix')->nullable();  // null, I, II, III
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wood_species');
    }
};
