<?php

use App\Enums\ProductionStatusEnum;
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
        Schema::create('milk_productions', function (Blueprint $table) {
            $table->id();
            $table->string('id_number')->nullable()->comment('Unique identifier for the production record');
            $table->date('collection_date')->nullable()->comment('Date of the milk collection');
            $table->string('program')->default(ProgramEnum::DEFAULT->value)->comment('Program of the production');
            $table->foreignId('herd_id')->nullable()->constrained()->comment('Reference to the milk collection herd');
            $table->char('production_code', 32)->nullable()->comment('Code identifier for the production record');
            $table->foreignId('center_id')->nullable()->constrained()->comment('Reference to the milk collection center');
            $table->foreignId('partner_id')->nullable()->constrained()->comment('Reference to the farmer or partner who supplied the milk');
            $table->foreignId('farmer_id')->nullable()->constrained()->comment('Reference to the farmer who supplied the milk');
            $table->date('transaction_date')->nullable()->comment('Date of the milk production record filed and transaction always greater than or equal to duration end');
            $table->smallInteger('year')->nullable()->comment('Year of the production: 2025');
            $table->tinyInteger('month')->nullable()->comment('Month of the production: 1-12');
            $table->tinyInteger('quarter')->nullable()->comment('Quarter of the production: 1-4');
            $table->tinyInteger('week')->nullable()->comment('Week of the production: 1-53');
            $table->string('period_start', 50)->nullable()->comment('Duration start period (e.g., Jan 10-16)');
            $table->string('period_end', 50)->nullable()->comment('Duration end period (e.g., Jan 10-16)');
            $table->decimal('am_volume', 10, 2)->nullable()->comment('(AM) milk volume in kilograms');
            $table->decimal('pm_volume', 10, 2)->nullable()->comment('(PM) milk volume in kilograms');
            $table->decimal('total_volume_produced', 12, 2)->nullable()->comment('Total milk volume produced in kilograms');
            $table->unsignedBigInteger('volume_from_farmers')->nullable()->comment('Milk volume collected directly from farmers');
            $table->string('milking_times')->nullable()->comment('Milking schedule (e.g., AM, PM, BOTH)');
            $table->string('source_of_milk')->nullable()->comment('Source or origin of the milk');
            $table->string('f1')->nullable()->comment('Field F1 - Used only for animal-level milk production records');
            $table->string('f2')->nullable()->comment('Field F2 - Used only for animal-level milk production records');
            $table->integer('number_of_lactating')->nullable()->comment('Number of lactating animals during production period');
            $table->decimal('avg_milk_per_animal_per_day', 10, 2)->nullable()->comment('Average milk yield per animal per day');
            $table->string('status')->default(ProductionStatusEnum::DEFAULT->value)->comment('Production status (e.g., ACTIVE, INACTIVE, etc.)');
            $table->text('notes')->nullable()->comment('Additional remarks or notes about production');
            $table->timestamps();

            $table->index(['center_id', 'year', 'month'], 'milk_productions_center_year_month_index');
            $table->index(['center_id', 'partner_id'], 'milk_productions_center_partner_index');
            $table->index(['center_id', 'farmer_id'], 'milk_productions_center_farmer_index');
            $table->index(['year', 'quarter'], 'milk_productions_year_quarter_index');
            $table->index('status', 'milk_productions_status_index');
            $table->index('collection_date', 'milk_productions_collection_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milk_productions');
    }
};
