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
        Schema::table('project_sites', function (Blueprint $table) {
            $table->foreignId('processing_partner_id')->nullable()->after('production_partner_id')->constrained('partners')->onDelete('set null')->comment('Reference to the partner for the processing infrastructure');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processing_partner_id');
        });
    }
};
