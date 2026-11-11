<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milk_utilization_monthly_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('partner_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('program')->nullable();
            $table->integer('year');
            $table->integer('month');

            $table->decimal('lactating_animals', 10, 2)->nullable()->comment('Column B: No. of Lactating Animals');
            $table->decimal('production', 15, 2)->nullable()->comment('Column D: Production');
            $table->decimal('received', 15, 2)->nullable()->comment('Column E: Received / Purchased');
            $table->decimal('fed_to_calves', 15, 2)->nullable()->comment('Column G: Fed to Calves');
            $table->decimal('value_added_products', 15, 2)->nullable()->comment('Column I: Value Added Products');
            $table->decimal('milk_feeding', 15, 2)->nullable()->comment('Column K: Milk Feeding');

            $table->string('lactating_animals_source')->default('manual')->comment('manual | aggregated');
            $table->string('production_source')->default('manual')->comment('manual | aggregated');
            $table->string('received_source')->default('manual')->comment('manual | aggregated');
            $table->string('fed_to_calves_source')->default('manual')->comment('manual | aggregated');
            $table->string('value_added_products_source')->default('manual')->comment('manual | aggregated');
            $table->string('milk_feeding_source')->default('manual')->comment('manual | aggregated');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['center_id', 'partner_id', 'year', 'month'], 'monthly_summary_unique');
            $table->index(['year', 'month'], 'monthly_summary_year_month');
            $table->index(['center_id', 'year', 'month'], 'monthly_summary_center');
            $table->index(['partner_id', 'year', 'month'], 'monthly_summary_partner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milk_utilization_monthly_summary');
    }
};
