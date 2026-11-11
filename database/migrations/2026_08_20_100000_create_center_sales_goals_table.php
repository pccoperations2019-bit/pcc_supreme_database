<?php

use App\Enums\ProgramEnum;
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
        Schema::create('center_sales_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained()->onDelete('cascade');
            $table->integer('year');
            $table->string('program')->default(ProgramEnum::DEFAULT->value);
            $table->decimal('milk_sales_goal', 15, 2)->nullable();
            $table->decimal('meat_sales_goal', 15, 2)->nullable();
            $table->decimal('manure_sales_goal', 15, 2)->nullable();
            $table->decimal('hide_sales_goals', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['center_id', 'year', 'program'], 'center_sales_goals_center_year_program_unique');
        });

        Schema::table('center_production_goals', function (Blueprint $table) {
            $table->dropColumn(['milk_sales_goal', 'meat_sales_goal', 'manure_sales_goal', 'hide_sales_goals']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('center_production_goals', function (Blueprint $table) {
            $table->decimal('milk_sales_goal', 15, 2)->nullable();
            $table->decimal('meat_sales_goal', 15, 2)->nullable();
            $table->decimal('manure_sales_goal', 15, 2)->nullable();
            $table->decimal('hide_sales_goals', 15, 2)->nullable();
        });

        Schema::dropIfExists('center_sales_goals');
    }
};
