<?php

use App\Enums\DairyBoxEnum;
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
        Schema::create('dairy_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('dairy_box_id')->nullable()->comment('Unique Dairy Box ID');
            $table->string('business_name')->nullable()->comment('Business name of the dairy box');
            $table->string('manager')->nullable()->comment('Manager name');
            $table->string('dairy_box_focal')->nullable()->comment('Dairy box focal person');
            $table->string('contact_number')->nullable()->comment('Contact number');
            $table->foreignId('center_id')->nullable()->constrained()->onDelete('set null')->comment('Associated center');
            $table->foreignId('partner_id')->nullable()->constrained()->onDelete('set null')->comment('Associated partner');
            $table->string('status')->default(DairyBoxEnum::DEFAULT->value)->comment('Operational status');
            $table->date('launched_date')->nullable()->comment('Date when dairy box was launched');
            $table->string('kadiwa_integration')->nullable()->comment('KADIWA Integration status');
            $table->string('milk_feeding_engagement')->nullable()->comment('Milk Feeding Engagement status');
            $table->string('bir_registered')->nullable()->comment('BIR Registered status');
            $table->string('fda_lto')->nullable()->comment('FDA LTO status');
            $table->string('cso_accreditation')->nullable()->comment('CSO Accreditation status');
            $table->string('philgeps')->nullable()->comment('PhilGeps status');
            $table->string('ffedis')->nullable()->comment('FFEDIS status');
            $table->string('fund_source_outlet')->nullable()->comment('Fund Source: Outlet');
            $table->string('fund_source_equipment')->nullable()->comment('Fund Source: Equipment');
            $table->string('fund_source_processing_plant')->nullable()->comment('Fund Source: Processing Plant');
            $table->string('land_ownership')->nullable()->comment('Land Ownership status');
            $table->date('start_of_construction')->nullable()->comment('Start of Construction');
            $table->date('date_of_completion')->nullable()->comment('Date of Completion');
            $table->string('contractor')->nullable()->comment('Contractor name');
            $table->date('date_inaugurated')->nullable()->comment('Date Inaugurated');
            $table->json('metadata')->nullable()->comment('Additional metadata in JSON format');
            $table->text('remarks')->nullable()->comment('Remarks - "Done" if completely accomplished');
            $table->timestamps();

            $table->index('dairy_box_id', 'idx_dairy_boxes_dairy_box_id');
            $table->index('center_id', 'idx_dairy_boxes_center_id');
            $table->index('partner_id', 'idx_dairy_boxes_partner_id');
            $table->index('status', 'idx_dairy_boxes_status');
            $table->index(['center_id', 'status'], 'idx_dairy_boxes_center_status');
            $table->index(['partner_id', 'status'], 'idx_dairy_boxes_partner_status');
            $table->index('launched_date', 'idx_dairy_boxes_launched_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dairy_boxes');
    }
};
