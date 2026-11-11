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
        Schema::create('centers', function (Blueprint $table) {
            $table->id();
            $table->char('code', 12)->unique();
            $table->string('name')->comment('Name of the center');
            $table->foreignId('agency_id')
                ->constrained('agencies')
                ->cascadeOnDelete()
                ->comment('Reference to the associated agency, deletes center if agency is deleted');
            $table->text('description')->nullable()->comment('Optional description of the center');
            $table->string('website')->nullable()->comment('Website URL of the center');
            $table->string('logo')->nullable()->comment('Path to the logo image of the center');
            $table->char('color', 7)->nullable()->comment('Hex color code representing the center');
            $table->string('contact_person')->nullable()->comment('Name of the contact person for the center');
            $table->string('contact_number')->nullable()->comment('Contact number of the center');
            $table->string('email_address')->nullable()->comment('Email address of the center');

            $table->json('metadata')->nullable()->comment('Additional metadata for the center in JSON format');
            $table->char('status', 16)->default(ProfileStatusEnum::DEFAULT->value)->comment('Status of the center, refer to ProfileStatusEnum');
            $table->char('category_size', 32)
                ->default(ProfileCategorySizeEnum::DEFAULT->value)->comment('Category of the center, refer to ProfileCategorySizeEnum');

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

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('centers');
    }
};
