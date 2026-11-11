<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('center_production_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained()->onDelete('cascade');
            $table->integer('year');
            $table->decimal('milk_production_goal', 15, 2)->nullable();
            $table->decimal('meat_meat_equivalent_goal', 15, 2)->nullable();
            $table->decimal('manure_based_goal', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['center_id', 'year'], 'center_production_goals_center_year_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('center_production_goals');
    }
};
