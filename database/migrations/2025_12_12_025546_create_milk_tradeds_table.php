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
        Schema::create('milk_tradeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milk_production_id')->nullable()->constrained()->cascadeOnDelete()->comment('Reference to related milk production record');
            $table->foreignId('center_id')->nullable()->constrained()->comment('Reference to the milk collection center');
            $table->foreignId('partner_id')->nullable()->constrained()->comment('Reference to the farmer or partner who supplied the milk');
            $table->foreignId('farmer_id')->nullable()->constrained()->comment('Reference to the farmer who supplied the milk');
            $table->date('transaction_date')->nullable()->comment('Date of the milk production record filed and transaction always greater than or equal to duration end');
            $table->smallInteger('year')->nullable()->comment('Year of the production: 2025');
            $table->tinyInteger('month')->nullable()->comment('Month of the production: 1-12');
            $table->tinyInteger('quarter')->nullable()->comment('Quarter of the production: 1-4');
            $table->tinyInteger('week')->nullable()->comment('Week of the production: 1-53');
            $table->date('period_start')->nullable()->comment('Duration start date');
            $table->date('period_end')->nullable()->comment('Duration end date');
            $table->decimal('total_volume_traded', 10, 2)->nullable()->comment('Total milk volume traded in kilograms');
            $table->timestamps();

            $table->index(['center_id', 'year', 'month'], 'milk_tradeds_center_year_month_index');
            $table->index(['center_id', 'partner_id'], 'milk_tradeds_center_partner_index');
            $table->index(['center_id', 'farmer_id'], 'milk_tradeds_center_farmer_index');
            $table->index(['period_start', 'period_end'], 'milk_tradeds_period_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milk_tradeds');
    }
};
