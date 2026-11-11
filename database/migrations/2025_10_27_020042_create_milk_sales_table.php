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
        Schema::create('milk_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milk_production_id')->nullable()->constrained()->cascadeOnDelete()->comment('Reference to related milk production record');
            $table->foreignId('center_id')->nullable()->constrained()->comment('Reference to the milk collection center');
            $table->foreignId('partner_id')->nullable()->constrained()->comment('Reference to the farmer or partner who supplied the milk');
            $table->foreignId('farmer_id')->nullable()->constrained()->comment('Reference to the farmer who supplied the milk');
            $table->string('program')->default(ProgramEnum::DEFAULT->value)->comment('Program of the production');
            $table->date('transaction_date')->nullable()->comment('Date of the milk production record filed and transaction always greater than or equal to duration end');
            $table->smallInteger('year')->nullable()->comment('Year of the production: 2025');
            $table->tinyInteger('month')->nullable()->comment('Month of the production: 1-12');
            $table->tinyInteger('quarter')->nullable()->comment('Quarter of the production: 1-4');
            $table->tinyInteger('week')->nullable()->comment('Week of the production: 1-53');
            $table->string('period_start', 50)->nullable()->comment('Duration start period (e.g., Jan 10-16)');
            $table->string('period_end', 50)->nullable()->comment('Duration end period (e.g., Jan 10-16)');
            $table->decimal('total_volume_sold', 10, 2)->nullable()->comment('Total milk volume sold in kilograms');
            $table->decimal('farm_gate_price', 8, 2)->nullable()->comment('Price per kilogram at farm gate');
            $table->decimal('raw_milk_traded_sales', 12, 2)->nullable()->comment('Computed as milk_traded * farm_gate_price');
            $table->decimal('value_added_products_sales', 12, 2)->nullable()->comment('Sales from value-added milk products');
            $table->decimal('dairy_box_sales', 12, 2)->nullable()->comment('Revenue from dairy box sales');
            $table->decimal('milk_sales', 12, 2)->nullable()->comment('Total sales from raw milk');
            $table->decimal('milk_feeding_sales', 12, 2)->nullable()->comment('Revenue from milk used for feeding');
            $table->decimal('sales_of_value_added', 12, 2)->nullable()->comment('Sales from value-added products');
            $table->decimal('value_of_total_milk_production', 12, 2)->nullable()->comment('Total monetary value of milk production');
            $table->timestamps();

            $table->index(['center_id', 'year', 'month'], 'milk_sales_center_year_month_index');
            $table->index(['center_id', 'partner_id'], 'milk_sales_center_partner_index');
            $table->index(['center_id', 'farmer_id'], 'milk_sales_center_farmer_index');
            $table->index(['year', 'quarter'], 'milk_sales_year_quarter_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milk_sales');
    }
};
