<?php

use App\Enums\CertificateStatusEnum;
use App\Enums\CivilStatusEnum;
use App\Enums\EducationalAttainmentEnum;
use App\Enums\EntrepreneurTypeEnum;
use App\Enums\FarmerCategoryEnum;
use App\Enums\IncomeSourceEnum;
use App\Enums\ManagementSystemEnum;
use App\Enums\ProfileCategorySizeEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProjectProgramEnum;
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
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->char('code', 32)->unique();
            $table->foreignId('center_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->foreignId('partner_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->date('date_of_interview')->nullable();
            $table->date('date_of_enrollment')->nullable();
            $table->string('operation_in_charge')->nullable();
            $table->boolean('is_assisted')->nullable();
            $table->string('project_category')->nullable()->default(ProjectProgramEnum::DEFAULT->value);
            $table->char('category', 32)
                ->default(FarmerCategoryEnum::DEFAULT->value)->comment('Refer to FarmerCategoryEnum');
            $table->char('status', 16)->default(ProfileStatusEnum::DEFAULT->value)->comment('Status of the center, refer to ProfileStatusEnum');
            $table->char('category_size', 32)
                ->default(ProfileCategorySizeEnum::DEFAULT->value)->comment('Category of the center, refer to ProfileCategorySizeEnum');
            /**
             * Personal Information
    | Field                              | Definition                               |
    | ---------------------------------- | ---------------------------------------- |
    | PHOTO                              | Photo of the farmer.                     |
    | LAST NAME                          | Surname of the farmer.                   |
    | FIRST NAME                         | Given name.                              |
    | MIDDLE INITIAL                     | Middle initial.                          |
    | NICKNAME                           | Known or preferred short name.           |
    | EXTENSION NAME                     | Jr., Sr., III, etc.                      |
    | AVAILABILITY OF PERSONAL CELLPHONE | Whether the farmer has a personal phone. |
    | CONTACT NUMBER                     | Phone number.                            |
    | REGION                             | Farmer’s regional location.              |
    | PROVINCE                           | Province location.                       |
    | DISTRICT                           | District.                                |
    | MUNICIPALITYCITY                   | Municipality or city.                    |
    | BARANGAY                           | Barangay.                                |
    | STREET                             | Street address.                          |
    | PUROKZONE                          | Purok or zone identifier.                |
    | ZIPCODE                            | Postal code.                             |
    | GENDER                             | Gender identity.                         |
    | SEX                                | Biological sex.                          |
    | BIRTHDAY                           | Date of birth.                           |
    | AGE                                | Age.                                     |
    | CIVIL STATUS                       | Marital status.                          |
    | SPOUSE NAME                        | Name of spouse.                          |
    | HOUSEHOLD SIZE                     | Number of people in the household.       |
             */
            $table->string('photo')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('nickname')->nullable();
            $table->string('extension_name')->nullable();
            $table->boolean('has_personal_cellphone')->nullable();
            $table->string('contact_number')->comment('phone number')->nullable();
            $table->string('gender')->nullable();
            $table->date('birthday')->nullable();
            $table->integer('age')->nullable();
            $table->string('civil_status')->nullable()->default(CivilStatusEnum::DEFAULT->value); // ENUM?
            $table->string('spouse_name')->nullable(); // name of spouse
            $table->smallInteger('household_size')->nullable();
            $table->string('purok_zone')->nullable();
            $table->string('zip_code')->nullable();
            /**
             * Socio-Economic Information
             * | Field                                | Definition                                         |
| ------------------------------------ | -------------------------------------------------- |
| POSITION                             | Role/position in the cooperative/organization.     |
| YEAR INVOLVEMENT IN THE BUSINESS     | Years of involvement in the business/farming.      |
| FAMILY MEMBER ATTENDING SCHOOL COUNT | Number of students in the household.               |
| FEMALE COUNT                         | Number of females in the household.                |
| MALE COUNT                           | Number of males in the household.                  |
| ISPWD                                | Indicates if a member is a Person with Disability. |
| IS4PS                                | Indicates if the household is part of 4Ps.         |
| ISIPS                                | Indicates if from Indigenous Peoples group.        |
| INDIGENIOUS GROUP                    | Name of the indigenous group.                      |
| EDUCATIONALATTAINMENT                | Highest education completed.                       |
| NDALTOCERTIFICATIONSTATUS            | Current status of NDALTO certification.            |
| NDALTOCERTIFICATION                  | NDALTO certification details.                      |
| TYPE OF ENTREPRENEUR                 | Categorization of the farmer as entrepreneur.      |
             */
            $table->string('position')->nullable();
            $table->smallInteger('year_involvement_in_business')->nullable();
            $table->smallInteger('family_member_attending_school_count')->nullable();
            $table->smallInteger('female_count')->nullable();
            $table->smallInteger('male_count')->nullable();
            $table->boolean('is_pwd')->default(false);
            $table->boolean('is_4ps')->default(false);
            $table->boolean('is_ips')->default(false);
            $table->string('indigenous_group')->nullable();
            $table->string('educational_attainment')->nullable()->default(EducationalAttainmentEnum::DEFAULT->value);
            $table->string('ndalto_certification_status')->nullable()->default(CertificateStatusEnum::DEFAULT->value);
            $table->string('ndalto_certification')->nullable();
            $table->string('type_of_entrepreneur')->nullable()->default(EntrepreneurTypeEnum::DEFAULT->value);

            /**
             * RSBSA & Production Information
             * | Field                                                                         | Definition                     |
| ----------------------------------------------------------------------------- | ------------------------------ |
| RSBSA CERT STATUS                                                             | Status of RSBSA certification. |
| RSBSA CERTIFICATE OF REGISTRATION                                             | Certificate details.           |
| IF PRODUCER PLEASE SPECIFY                                                    | Type of producer activities.   |
| INVOLVED IN BUFFALO FARMING?                                                  | Yes/No.                        |
| HOW MANY IN THE FAMILY ARE INVOLVED IN BUFFALO FARMING?                       | Count.                         |
| ARE YOU THE HEAD OF THE FAMILY?                                               | Yes/No.                        |
| IF NO, WHAT IS THE HIGHEST EDUCATIONAL ATTAINMENT OF THE HEAD OF YOUR FAMILY? | Education of family head.      |
| CHALLENGES OR PROBLEMS ENCOUNTERED IN DAIRY FARMING?                          | Farmer’s reported challenges.  |
             */
            $table->string('rsbsa_cert_status')->nullable()->default(CertificateStatusEnum::DEFAULT->value);
            $table->string('rsbsa_certificate_of_registration')->nullable();
            $table->string('if_producer_please_specify')->nullable();
            $table->boolean('involved_in_buffalo_farming')->default(false);
            $table->smallInteger('how_many_in_family_are_involved_in_buffalo_farming')->nullable();
            $table->boolean('are_you_the_head_of_the_family')->default(false);
            $table->string('if_no_highest_educational_attainment_of_head_of_family')->nullable();
            $table->text('challenges_or_problems_encountered_in_dairy_farming')->nullable();

            /** Income Profile
             * | Field                        | Definition                     |
| ---------------------------- | ------------------------------ |
| MAIN SOURCE OF INCOME        | Main livelihood.               |
| MAIN ANNUAL NET INCOME       | Net income from main source.   |
| SECONDARY SOURCE OF INCOME   | Second livelihood.             |
| SECONDARY ANNUAL NET INCOME  | Income from secondary source.  |
| TERTIARY SOURCE OF INCOME    | Third livelihood.              |
| TERTIARY ANNUAL NET INCOME   | Income from tertiary source.   |
| QUATERNARY SOURCE OF INCOME  | Additional source.             |
| QUATERNARY ANNUAL NET INCOME | Income from quaternary source. |
| AVG NET INCOME               | Average computed income.       |
| TOTAL NET INCOME             | Annual total income.           |
             */
            $table->string('main_source_of_income')->nullable()->default(IncomeSourceEnum::DEFAULT->value);
            $table->decimal('main_annual_net_income', 15, 2)->nullable();
            $table->string('secondary_source_of_income')->nullable()->default(IncomeSourceEnum::DEFAULT->value);
            $table->decimal('secondary_annual_net_income', 15, 2)->nullable();
            $table->string('tertiary_source_of_income')->nullable()->default(IncomeSourceEnum::DEFAULT->value);
            $table->decimal('tertiary_annual_net_income', 15, 2)->nullable();
            $table->string('quaternary_source_of_income')->nullable()->default(IncomeSourceEnum::DEFAULT->value);
            $table->decimal('quaternary_annual_net_income', 15, 2)->nullable();
            $table->decimal('avg_net_income', 15, 2)->nullable();
            $table->decimal('total_net_income', 15, 2)->nullable();
            $table->smallInteger('staff_count_male')->nullable();
            $table->smallInteger('staff_count_female')->nullable();
            $table->decimal('ave_monthly_salary', 15, 2)->nullable();
            $table->string('status_google_map')->nullable();
            $table->string('google_map_link')->nullable();
            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();
            $table->string('geotag_image')->nullable();

            /** Farm SYSTEM
             * | Category                           | Definition                                          |
| ---------------------------------- | --------------------------------------------------- |
| MANAGEMENTSYSTEM                   | Overall management system used.                     |
| ISDRYLOT                           | Uses dry lot system.                                |
| DURATIONOFSTOCKING                 | Stocking duration.                                  |
| ISAPPLIEDINTHEFIELD                | Applied in-field practices.                         |
| ISDRYRICEFIELD–ISDRYVEGETABLE      | Dry feeding material availability.                  |
| ISVERMICOMPOSTING                  | Use of vermicomposting.                             |
| ISPASTURERANGEPADDOCK              | Uses pasture paddock.                               |
| ISDAILYSPREAD–ISSTORAGEBULKING     | Solid waste systems.                                |
| ISLIQUIDSLURRY–ISANAEROBICDIGESTER | Liquid waste systems.                               |
| Forages (RAINY & DRY)              | List of available forage and feed types per season. |
| SILAGESPECFICATION                 | Silage type/specification.                          |
             */
            $table->string('management_system')->nullable()->default(ManagementSystemEnum::DEFAULT->value);
            $table->boolean('is_drylot')->default(false);
            $table->smallInteger('duration_of_stocking')->nullable();
            $table->boolean('is_applied_in_the_field')->default(false);
            $table->boolean('is_dry_rice_field')->default(false);
            $table->boolean('is_dry_vegetable')->default(false);
            $table->boolean('is_vermicomposting')->default(false);
            $table->boolean('is_pasture_range_paddock')->default(false);
            $table->boolean('is_daily_spread')->default(false);
            $table->boolean('is_storage_bulking')->default(false);
            $table->boolean('is_liquid_slurry')->default(false);
            $table->boolean('is_anaerobic_digester')->default(false);
            $table->text('forages_rainy_and_dry')->nullable();
            $table->string('silage_specification')->nullable();

            /**
             * LIVESTOCK
             * | Field          | Definition                         |
| -------------- | ---------------------------------- |
| DAIRYCATTLE    | Count or presence of dairy cattle. |
| BEEFCATTLE     | Count of beef cattle.              |
| GOAT           | Goat count.                        |
| CHICKEN        | Chicken count.                     |
| DUCK           | Duck count.                        |
| GEESE          | Goose count.                       |
| SWINE          | Swine count.                       |
| HORSE          | Horse count.                       |
| OTHERLIVESTOCK | Other animals.                     |
             */
            $table->integer('dairy_cattle')->nullable();
            $table->integer('beef_cattle')->nullable();
            $table->integer('goat')->nullable();
            $table->integer('chicken')->nullable();
            $table->integer('duck')->nullable();
            $table->integer('geese')->nullable();
            $table->integer('swine')->nullable();
            $table->integer('horse')->nullable();
            $table->integer('other_livestock')->nullable();

            $table->boolean('is_processing_partner')->default(false);
            $table->boolean('is_marketing_partner')->default(false);
            $table->boolean('is_dairybox')->default(false);
            $table->boolean('is_knpo_partner')->default(false);
            $table->boolean('is_fca_partner')->default(false);

            $table->boolean('has_cso')->default(false);
            $table->boolean('has_fda_lto')->default(false);
            $table->boolean('has_nda_lto')->default(false);
            $table->boolean('has_philgeps')->default(false);
            $table->boolean('has_rsbsa')->default(false);
            $table->boolean('has_ffedis')->default(false);
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['center_id', 'status'], 'farmers_center_status_index');
            $table->index(['center_id', 'partner_id'], 'farmers_center_partner_index');
            $table->index('status', 'farmers_status_index');
            $table->index('category', 'farmers_category_index');
            $table->index(['last_name', 'first_name'], 'farmers_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farmers');
    }
};
