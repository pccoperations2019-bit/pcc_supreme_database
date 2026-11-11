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
            $table->dropForeign(['partner_id']);

            $table->renameColumn('partner_id', 'production_partner_id');

            $table->foreign('production_partner_id')->references('id')->on('partners')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_sites', function (Blueprint $table) {
            $table->dropForeign(['production_partner_id']);

            $table->renameColumn('production_partner_id', 'partner_id');

            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('set null');
        });
    }
};
