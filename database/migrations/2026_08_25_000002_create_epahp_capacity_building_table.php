<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epahp_capacity_building', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->nullable()->constrained()->comment('Reference to the center associated with the record');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('number_of_cooperatives')->default(0);
            $table->unsignedInteger('number_of_farmers_trained')->default(0);
            $table->unsignedInteger('training_conducted')->default(0);
            $table->decimal('pcc_budget_allocated', 18, 2)->default(0);
            $table->foreignId('partner_id')->nullable()->constrained('partners')->onDelete('set null')->comment('Reference to the partner associated with the record');
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->onDelete('set null')->comment('Reference to the farmer associated with the record');
            $table->foreignId('project_site_id')->nullable()->constrained('project_sites')->onDelete('set null')->comment('Reference to the project site associated with the record');
            $table->json('metadata')->nullable()->comment('Additional metadata for the record');

            $table->timestamps();

            $table->index(['partner_id'], 'epahp_cb_partner_id_index');
            $table->index(['farmer_id'], 'epahp_cb_farmer_id_index');
            $table->index(['project_site_id'], 'epahp_cb_project_site_id_index');

            $table->index(['center_id', 'year'], 'epahp_cb_center_year_index');
            $table->index(['year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epahp_capacity_building');
    }
};
