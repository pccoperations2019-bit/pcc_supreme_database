<?php

use App\Enums\TvTypeEnum;
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
        Schema::table('farmers', function (Blueprint $table) {
            $table->json('source_of_income_details')->nullable()->comment('Sources of income per household member');
            $table->string('membership_in_organization')->nullable()->comment('Membership in organization');
            $table->string('name_of_organization')->nullable()->comment('Name of organization');
            $table->boolean('is_indigent')->default(false)->comment('Indigent Person');
            $table->string('tv_type')->default(TvTypeEnum::DEFAULT->value)->comment('Type of TV');

            // SECTION V - Farm Information
            $table->string('proposed_farm_name')->nullable()->comment('Proposed Farm Name (if Farm Name is none)');
            $table->string('farm_district')->nullable()->comment('Farm Address District');
            $table->string('farm_street')->nullable()->comment('Farm Address Zone/Street/Sitio/Subdivision');
            $table->json('type_of_farm_business_registration')->nullable()->comment('Types of farm business registration (multi-select)');
            $table->string('status_of_registration')->nullable()->comment('Status of registration');
            $table->string('farm_registration_number')->nullable()->comment('Registration/Serial/Certification No.');
            $table->smallInteger('year_farm_established')->nullable()->comment('Year the dairy carabao farm was established');
            $table->json('animal_inventory')->nullable()->comment('Animal inventory rows (classification, count, breed, remarks)');

            // SECTION 12 sub-section - Number of Animals According to Ownership
            $table->json('animal_ownership')->nullable()->comment('Animal counts per ownership type (checkbox + count pairs)');

            // SECTION 14 - Feeding Management
            $table->string('feeding_management')->nullable()->comment('Feeding management system');

            // SECTION 17 - Twice-a-day Milking
            $table->boolean('is_twice_a_day_milking')->nullable()->comment('Milks the animal twice a day (Yes/No)');

            // SECTION 18 - Daily Milk Production (estimated liters per day)
            $table->decimal('daily_milk_production_min', 10, 2)->nullable()->comment('Minimum estimated daily milk production in liters');
            $table->decimal('daily_milk_production_max', 10, 2)->nullable()->comment('Maximum estimated daily milk production in liters');

            // SECTION 19 - Milk Price per Liter (estimated range)
            $table->decimal('milk_price_per_liter_min', 10, 2)->nullable()->comment('Minimum estimated milk price per liter');
            $table->decimal('milk_price_per_liter_max', 10, 2)->nullable()->comment('Maximum estimated milk price per liter');

            // SECTION 20 - Where Do You Sell Your Milk Produce? (multi-select)
            $table->json('milk_selling_locations')->nullable()->comment('Where the farmer sells milk produce (multi-select)');
            $table->string('milk_selling_restaurant_specify')->nullable()->comment('Known restaurant/establishment name');
            $table->string('milk_selling_processor_specify')->nullable()->comment('Private processor name');
            $table->string('milk_selling_collector_name')->nullable()->comment('Independent milk collector name');
            $table->string('milk_selling_others_specify')->nullable()->comment('Other milk selling location');

            // SECTION 23 - Assistance Needed from Government (4 numbered free-text lines)
            $table->json('assistance_needed')->nullable()->comment('Assistance needed from government (array of 4 free-text lines)');

            // pcc assets tab for pcc assets latitude and longitude
            $table->decimal('pcc_assets_latitude', 10, 7)->nullable()->comment('Latitude of the PCC assets');
            $table->decimal('pcc_assets_longitude', 10, 7)->nullable()->comment('Longitude of the PCC assets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropColumn([
                'source_of_income_details',
                'membership_in_organization',
                'name_of_organization',
                'is_indigent',
                'tv_type',
                'proposed_farm_name',
                'farm_district',
                'farm_street',
                'type_of_farm_business_registration',
                'status_of_registration',
                'farm_registration_number',
                'year_farm_established',
                'animal_inventory',
                'animal_ownership',
                'feeding_management',
                'is_twice_a_day_milking',
                'daily_milk_production_min',
                'daily_milk_production_max',
                'milk_price_per_liter_min',
                'milk_price_per_liter_max',
                'milk_selling_locations',
                'milk_selling_restaurant_specify',
                'milk_selling_processor_specify',
                'milk_selling_collector_name',
                'milk_selling_others_specify',
                'assistance_needed',
            ]);
        });
    }
};
