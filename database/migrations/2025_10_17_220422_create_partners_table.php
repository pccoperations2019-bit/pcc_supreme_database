<?php

use App\Enums\ProfileCategorySizeEnum;
use App\Enums\ProfileStatusEnum;
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
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->char('code', 32)->unique()->nullable();
            $table->foreignId('center_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->char('status', 16)->default(ProfileStatusEnum::DEFAULT->value)->comment('Status of the center, refer to ProfileStatusEnum');
            $table->char('category_size', 32)
                ->default(ProfileCategorySizeEnum::DEFAULT->value)->comment('Category of the center, refer to ProfileCategorySizeEnum');
            $table->char('category', 32)->nullable();
            $table->char('partnership_type', 32)->nullable();
            $table->string('website')->nullable();
            $table->string('logo')->comment('Path to logo image')->nullable();
            $table->text('description')->nullable();

            // primary cotneact
            $table->string('contact_name')->nullable();
            $table->string('position')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('telephone_number')->nullable();
            // demographics
            $table->unsignedInteger('total_composition')->default(0)->comment('Total number of members in the partner organization');
            $table->unsignedInteger('male_count')->default(0)->comment('Number of male members');
            $table->unsignedInteger('female_count')->default(0)->comment('Number of female members');
            $table->decimal('avg_monthly_income', 10, 2)->default(0.00)->comment('Average monthly income of members');

            $table->unsignedInteger('total_farmers')->default(0)->comment('Total number of farmers associated with the center');

            // partnerships & compliances
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

            $table->index(['center_id', 'status'], 'partners_center_status_index');
            $table->index('status', 'partners_status_index');
            $table->index('category', 'partners_category_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
