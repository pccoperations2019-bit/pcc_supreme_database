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
        // milk_sales table
        Schema::table('milk_sales', function (Blueprint $table) {
            $table->decimal('total_volume_sold', 40, 2)->nullable()->change();
            $table->decimal('farm_gate_price', 40, 2)->nullable()->change();
            $table->decimal('raw_milk_traded_sales', 40, 2)->nullable()->change();
            $table->decimal('value_added_products_sales', 40, 2)->nullable()->change();
            $table->decimal('dairy_box_sales', 40, 2)->nullable()->change();
            $table->decimal('milk_sales', 40, 2)->nullable()->change();
            $table->decimal('milk_feeding_sales', 40, 2)->nullable()->change();
            $table->decimal('sales_of_value_added', 40, 2)->nullable()->change();
            $table->decimal('value_of_total_milk_production', 40, 2)->nullable()->change();
        });

        // milk_productions table
        Schema::table('milk_productions', function (Blueprint $table) {
            $table->decimal('am_volume', 40, 2)->nullable()->change();
            $table->decimal('pm_volume', 40, 2)->nullable()->change();
            $table->decimal('total_volume_produced', 40, 2)->nullable()->change();
            $table->decimal('avg_milk_per_animal_per_day', 40, 2)->nullable()->change();
            $table->bigInteger('number_of_lactating')->nullable()->change();
        });

        // milk_wastages table
        Schema::table('milk_wastages', function (Blueprint $table) {
            $table->decimal('milk_spoiled_volume', 40, 2)->default(0)->change();
            $table->decimal('milk_spoiled_cost', 40, 2)->default(0)->change();
            $table->decimal('milk_spoiled_price', 40, 2)->default(0)->change();
        });

        // milk_costs table
        Schema::table('milk_costs', function (Blueprint $table) {
            $table->decimal('raw_milk_cost', 40, 2)->default(0)->change();
            $table->decimal('raw_milk_price', 40, 2)->default(0)->change();
            $table->decimal('purchased_raw_milk_volume', 40, 2)->default(0)->change();
            $table->decimal('avg_salary_of_staff', 40, 2)->default(0)->change();
            $table->bigInteger('number_of_staff_male')->default(0)->change();
            $table->bigInteger('number_of_staff_female')->default(0)->change();
        });

        // milk_usages table
        Schema::table('milk_usages', function (Blueprint $table) {
            $table->decimal('fed_to_calves', 40, 2)->default(0)->change();
            $table->decimal('family_consumption', 40, 2)->default(0)->change();
        });

        // milk_tradeds table
        Schema::table('milk_tradeds', function (Blueprint $table) {
            $table->decimal('total_volume_traded', 40, 2)->nullable()->change();
        });

        // milk_feeding_programs table
        Schema::table('milk_feeding_programs', function (Blueprint $table) {
            $table->decimal('total_volume_requirements', 40, 2)->default(0)->change();
            $table->decimal('raw_milk_used', 40, 2)->default(0)->change();
            $table->decimal('whole_milk', 40, 2)->default(0)->change();
            $table->decimal('skimmed_milk', 40, 2)->default(0)->change();
            $table->decimal('sugar', 40, 2)->default(0)->change();
            $table->decimal('price_per_milk_pack', 40, 2)->default(0)->change();
            $table->decimal('total_contract_amount', 40, 2)->default(0)->change();
            $table->decimal('service_fee', 40, 2)->default(0)->change();
            $table->decimal('gross_income', 40, 2)->default(0)->change();
            $table->bigInteger('feeding_days')->default(0)->change();
            $table->bigInteger('number_of_beneficiaries')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // milk_sales table
        Schema::table('milk_sales', function (Blueprint $table) {
            $table->decimal('total_volume_sold', 10, 2)->nullable()->change();
            $table->decimal('farm_gate_price', 8, 2)->nullable()->change();
            $table->decimal('raw_milk_traded_sales', 12, 2)->nullable()->change();
            $table->decimal('value_added_products_sales', 12, 2)->nullable()->change();
            $table->decimal('dairy_box_sales', 12, 2)->nullable()->change();
            $table->decimal('milk_sales', 12, 2)->nullable()->change();
            $table->decimal('milk_feeding_sales', 12, 2)->nullable()->change();
            $table->decimal('sales_of_value_added', 12, 2)->nullable()->change();
            $table->decimal('value_of_total_milk_production', 12, 2)->nullable()->change();
        });

        // milk_productions table
        Schema::table('milk_productions', function (Blueprint $table) {
            $table->decimal('am_volume', 10, 2)->nullable()->change();
            $table->decimal('pm_volume', 10, 2)->nullable()->change();
            $table->decimal('total_volume_produced', 12, 2)->nullable()->change();
            $table->decimal('avg_milk_per_animal_per_day', 10, 2)->nullable()->change();
            $table->integer('number_of_lactating')->nullable()->change();
        });

        // milk_wastages table
        Schema::table('milk_wastages', function (Blueprint $table) {
            $table->decimal('milk_spoiled_volume', 10, 2)->default(0)->change();
            $table->decimal('milk_spoiled_cost', 10, 2)->default(0)->change();
            $table->decimal('milk_spoiled_price', 10, 2)->default(0)->change();
        });

        // milk_costs table
        Schema::table('milk_costs', function (Blueprint $table) {
            $table->decimal('raw_milk_cost', 10, 2)->default(0)->change();
            $table->decimal('raw_milk_price', 10, 2)->default(0)->change();
            $table->decimal('purchased_raw_milk_volume', 10, 2)->default(0)->change();
            $table->decimal('avg_salary_of_staff', 10, 2)->default(0)->change();
            $table->integer('number_of_staff_male')->default(0)->change();
            $table->integer('number_of_staff_female')->default(0)->change();
        });

        // milk_usages table
        Schema::table('milk_usages', function (Blueprint $table) {
            $table->decimal('fed_to_calves', 10, 2)->default(0)->change();
            $table->decimal('family_consumption', 10, 2)->default(0)->change();
        });

        // milk_tradeds table
        Schema::table('milk_tradeds', function (Blueprint $table) {
            $table->decimal('total_volume_traded', 10, 2)->nullable()->change();
        });

        // milk_feeding_programs table
        Schema::table('milk_feeding_programs', function (Blueprint $table) {
            $table->decimal('total_volume_requirements', 15, 2)->default(0)->change();
            $table->decimal('raw_milk_used', 15, 2)->default(0)->change();
            $table->decimal('whole_milk', 15, 2)->default(0)->change();
            $table->decimal('skimmed_milk', 15, 2)->default(0)->change();
            $table->decimal('sugar', 15, 2)->default(0)->change();
            $table->decimal('price_per_milk_pack', 10, 2)->default(0)->change();
            $table->decimal('total_contract_amount', 15, 2)->default(0)->change();
            $table->decimal('service_fee', 15, 2)->default(0)->change();
            $table->decimal('gross_income', 15, 2)->default(0)->change();
            $table->integer('feeding_days')->default(0)->change();
            $table->integer('number_of_beneficiaries')->default(0)->change();
        });
    }
};
