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
        Schema::table('service_records', function (Blueprint $table) {
            $table->string('unit_section')->nullable()->comment('Unit / Section');
            $table->string('ai_technician_staff')->nullable()->comment('AI TECHNICIAN / STAFF INVOLVED');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->onDelete('cascade')->comment('COOP/ ASSO/SCHOOL/UNIVERSITY');
            $table->string('animal_name')->nullable()->comment('Name of Animal');
            $table->string('bcs')->nullable()->comment('BCS');
            $table->string('semen_used')->nullable()->comment('Semen Used IF AIed');
            $table->string('bull_id_owner')->nullable()->comment('BULL ID or OWNER OF BULL FOR NM');
            $table->date('date_ai_nm')->nullable()->comment('Date of AI/NM');
            $table->boolean('pregnant')->nullable()->comment('PREGNANT');
            $table->date('date_of_calving')->nullable()->comment('Date of Calving');
            $table->string('calving_sex')->nullable()->comment('Sex of the Calving');
            $table->string('date_of_calving_sex')->nullable()->comment('Date of Calving and Sex');
            $table->string('biological_used')->nullable()->comment('Biological Used');
            $table->string('transfer_to')->nullable()->comment('Transfer to');
            $table->string('contact_number')->nullable()->comment('Contact Number (Tel./CP#)');
            $table->string('email')->nullable()->comment('e-mail address');
            $table->json('services_metadata')->nullable()->comment('SERVICES AND TYPE OF SERVICES');
            $table->json('metadata')->nullable()->comment('Flexible metadata, e.g. Remarks, Remarks as of Monitoring Date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_id');
            $table->dropColumn([
                'unit_section',
                'ai_technician_staff',
                'animal_name',
                'bcs',
                'semen_used',
                'bull_id_owner',
                'date_ai_nm',
                'pregnant',
                'date_of_calving',
                'calving_sex',
                'biological_used',
                'transfer_to',
                'contact_number',
                'email',
                'metadata',
            ]);
        });
    }
};
