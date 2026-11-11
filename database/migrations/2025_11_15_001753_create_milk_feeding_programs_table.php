<?php

use App\Enums\MilkFeedingStatusEnum;
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
        /**
         * Year	Funded By	Region	Center	Division	Province/ Municipality	Milk Packs	Total Volume Requirements 	Raw Milk Used in Liters	Whole Milk (kg)	Skimmed Milk (kg)	Sugar	Feeding Days	Beneficiaries	Milk Type	Price	Supplier	Gross Income of Suppliers	Service Fee/ Admin Cost	Contract Amount	Mode of Procurement	MOA Signing	Fund Transfer	Date Started	Date Completed	Liquidation
         */
        Schema::create('milk_feeding_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->comment('Reference to the project id associated with the milk feeding program');
            // References
            $table->foreignId('center_id')->constrained()->comment('Reference to the center id involved in the program');
            $table->foreignId('partner_id')->nullable()->constrained()->comment('Reference to the partner or supplier involved in the program');
            $table->string('program')->default(ProgramEnum::DEFAULT->value)->comment('Program category or type of the feeding program');
            // General Information
            $table->string('name')->comment('Name of the feeding program');
            $table->text('description')->nullable()->comment('Description of the feeding program');
            $table->string('elementary_school')->nullable()->comment('Elementary school where the program is conducted');
            $table->string('division')->nullable()->comment('School Division Office (SDO) division');
            $table->year('year')->comment('Year the program was conducted');
            $table->char('funded_by', 32)->nullable()->comment('Entity or organization funding the program');

            // Volume and Milk Details
            $table->bigInteger('milk_packs')->default(0)->comment('Number of milk packs provided in the program');
            $table->decimal('total_volume_requirements', 15, 2)->default(0)->comment('Total volume requirements in liters for the feeding program');
            $table->decimal('raw_milk_used', 15, 2)->default(0)->comment('Total raw milk used in liters for the feeding program');
            $table->decimal('whole_milk', 15, 2)->default(0)->comment('Total whole milk used in kilograms for the feeding program');
            $table->decimal('skimmed_milk', 15, 2)->default(0)->comment('Total skimmed milk used in kilograms for the feeding program');
            $table->decimal('sugar', 15, 2)->default(0)->comment('Total sugar used in kilograms for the feeding program');
            $table->char('milk_type', 32)->nullable()->comment('Type of milk provided in the program');
            $table->char('milk_subcategory', 32)->nullable()->comment('Subcategory of milk provided in the program');
            $table->decimal('price_per_milk_pack', 10, 2)->default(0)->comment('Price per milk pack provided in the program');

            // Beneficiaries and Feeding Details
            $table->integer('feeding_days')->default(0)->comment('Number of feeding days in the program');
            $table->integer('number_of_beneficiaries')->default(0)->comment('Number of beneficiaries in the program');

            // Financial Details
            $table->decimal('total_contract_amount', 15, 2)->default(0)->comment('Total contract amount for the feeding program');
            $table->decimal('service_fee', 15, 2)->default(0)->comment('Service fee or admin cost for the program');
            $table->decimal('gross_income', 15, 2)->default(0)->comment('Gross income of suppliers involved in the program');
            $table->date('liquidation_date')->nullable()->comment('Date of liquidation');

            // Procurement and Agreements
            $table->char('mode_of_procurement', 64)->nullable()->comment('Mode of procurement for the program');
            $table->date('moa_signing_date')->nullable()->comment('Date of MOA signing');
            $table->date('fund_transfer_date')->nullable()->comment('Date of fund transfer');

            // Program Timeline
            $table->date('date_started')->nullable()->comment('Date the program started');
            $table->date('date_completed')->nullable()->comment('Date the program was completed');

            // Status
            $table->string('status')->default(MilkFeedingStatusEnum::PLANNED->value)->comment('Current status of the feeding program');

            $table->timestamps();

            $table->index('funded_by', 'milk_feeding_programs_funded_by_index');
            $table->index('division', 'milk_feeding_programs_division_index');
            $table->index('milk_type', 'milk_feeding_programs_milk_type_index');
            $table->index('milk_subcategory', 'milk_feeding_programs_milk_subcategory_index');
            $table->index('mode_of_procurement', 'milk_feeding_programs_mode_of_procurement_index');
            $table->index('year', 'milk_feeding_programs_year_index');
            $table->index('name', 'milk_feeding_programs_name_index');
            $table->index('elementary_school', 'milk_feeding_programs_elementary_school_index');
            $table->index('partner_id', 'milk_feeding_programs_partner_id_index');
            $table->index('date_started', 'milk_feeding_programs_date_started_index');
            $table->index('date_completed', 'milk_feeding_programs_date_completed_index');
            $table->index('liquidation_date', 'milk_feeding_programs_liquidation_date_index');
            $table->index('moa_signing_date', 'milk_feeding_programs_moa_signing_date_index');
            $table->index('fund_transfer_date', 'milk_feeding_programs_fund_transfer_date_index');
            $table->index(['center_id', 'year'], 'milk_feeding_programs_center_year_index');
            $table->index(['project_id', 'year'], 'milk_feeding_programs_project_year_index');
            $table->index(['funded_by', 'year'], 'milk_feeding_programs_funded_by_year_index');
            $table->index(['center_id', 'funded_by'], 'milk_feeding_programs_center_funded_by_index');
            $table->index(['division', 'year'], 'milk_feeding_programs_division_year_index');
            $table->index(['milk_type', 'year'], 'milk_feeding_programs_milk_type_year_index');
            $table->index(['mode_of_procurement', 'year'], 'milk_feeding_programs_mode_of_procurement_year_index');
            $table->index(['status', 'year'], 'milk_feeding_programs_status_year_index');
            $table->index(['date_started', 'date_completed'], 'milk_feeding_programs_timeline_index');
            $table->index(['created_at'], 'milk_feeding_programs_created_at_index');
            $table->index(['status', 'created_at'], 'milk_feeding_programs_status_created_at_index');
            $table->index(['year', 'created_at'], 'milk_feeding_programs_year_created_at_index');
            $table->index(['total_contract_amount'], 'milk_feeding_programs_total_contract_amount_index');
            $table->index(['gross_income'], 'milk_feeding_programs_gross_income_index');
            $table->index(['service_fee'], 'milk_feeding_programs_service_fee_index');

            // Status index (keeping the original)
            $table->index('status', 'milk_feeding_programs_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milk_feeding_programs');
    }
};
