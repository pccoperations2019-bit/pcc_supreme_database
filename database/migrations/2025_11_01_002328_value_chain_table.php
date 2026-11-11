<?php

use App\Enums\ConsolidationCategoryEnum;
use App\Enums\HerdTypeEnum;
use App\Enums\ProcessingCategoryEnum;
use App\Enums\ProductionCategoryEnum;
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
        Schema::create('production_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->onDelete('cascade');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->onDelete('cascade');
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->char('category', 32)->default(ProductionCategoryEnum::DEFAULT)->comment('Refer to ProductionCategoryEnum');
            $table->char('herd_type', 32)->default(HerdTypeEnum::DEFAULT)->comment('Refer to HerdTypeEnum');
            $table->date('date_of_establishment')->nullable();
            $table->boolean('within_center')->default(true);
            $table->string('contact_person')->nullable();
            $table->string('position')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('telephone_number')->nullable();
            $table->string('email_address')->nullable();
            $table->string('website')->nullable();
            $table->integer('number_of_staff_male')->default(0);
            $table->integer('number_of_staff_female')->default(0);
            $table->decimal('avg_salary_of_staff', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['center_id', 'category'], 'production_units_center_category_index');
            $table->index(['center_id', 'herd_type'], 'production_units_center_herd_type_index');
            $table->index(['partner_id', 'farmer_id'], 'production_units_partner_farmer_index');
        });

        Schema::create('consolidation_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->onDelete('cascade');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->onDelete('cascade');
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->char('category', 32)->default(ConsolidationCategoryEnum::DEFAULT)->comment('Refer to ConsolidationCategoryEnum');
            $table->date('date_of_establishment')->nullable();
            $table->boolean('within_center')->default(true);
            $table->string('contact_person')->nullable();
            $table->string('position')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('telephone_number')->nullable();
            $table->string('email_address')->nullable();
            $table->string('website')->nullable();
            $table->integer('number_of_staff_male')->default(0);
            $table->integer('number_of_staff_female')->default(0);
            $table->decimal('avg_salary_of_staff', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['center_id', 'category'], 'consolidation_units_center_category_index');
            $table->index(['partner_id', 'farmer_id'], 'consolidation_units_partner_farmer_index');
        });

        Schema::create('processing_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->onDelete('cascade');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->onDelete('cascade');
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->char('category', 32)->default(ProcessingCategoryEnum::DEFAULT)->comment('Refer to ProcessingCategoryEnum');
            $table->date('date_of_establishment')->nullable();
            $table->boolean('within_center')->default(true);
            $table->string('contact_person')->nullable();
            $table->string('position')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('telephone_number')->nullable();
            $table->string('email_address')->nullable();
            $table->string('website')->nullable();
            $table->integer('number_of_staff_male')->default(0);
            $table->integer('number_of_staff_female')->default(0);
            $table->decimal('avg_salary_of_staff', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['center_id', 'category'], 'processing_units_center_category_index');
            $table->index(['partner_id', 'farmer_id'], 'processing_units_partner_farmer_index');
        });

        Schema::create('marketing_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->onDelete('cascade');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->onDelete('cascade');
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('date_of_establishment')->nullable();
            $table->boolean('within_center')->default(true);
            $table->string('contact_person')->nullable();
            $table->string('position')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('telephone_number')->nullable();
            $table->string('email_address')->nullable();
            $table->string('website')->nullable();
            $table->integer('number_of_staff_male')->default(0);
            $table->integer('number_of_staff_female')->default(0);
            $table->decimal('avg_salary_of_staff', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['center_id'], 'marketing_units_center_index');
            $table->index(['partner_id', 'farmer_id'], 'marketing_units_partner_farmer_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
