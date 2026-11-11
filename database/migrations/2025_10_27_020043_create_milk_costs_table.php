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
        Schema::create('milk_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milk_production_id')->constrained()->cascadeOnDelete()->comment('Reference to the related milk production record');
            $table->decimal('raw_milk_cost', 10, 2)->default(0)->comment('Total cost incurred in purchasing raw milk');
            $table->decimal('raw_milk_price', 10, 2)->default(0)->comment('Selling price per kilogram of processed milk');
            $table->decimal('purchased_raw_milk_volume', 10, 2)->default(0)->comment('Volume of raw milk purchased in kilograms');
            $table->integer('number_of_staff_male')->default(0)->comment('Number of male staff involved in production');
            $table->integer('number_of_staff_female')->default(0)->comment('Number of female staff involved in production');
            $table->decimal('avg_salary_of_staff', 10, 2)->default(0)->comment('Average monthly salary of staff involved in milk production');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milk_costs');
    }
};
