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
        Schema::create('project_site_marketing_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_site_id')->nullable()->constrained('project_sites')->onDelete('set null')->comment('Reference to project site');
            $table->string('staff_name')->nullable()->comment('Name of Staff');
            $table->string('contact_number')->nullable()->comment('Contact Number');
            $table->integer('number_of_staff')->default(0)->comment('Number of Marketing Staff');
            $table->json('metadata')->nullable()->comment('Additional metadata in JSON format');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_site_marketing_staff');
    }
};
