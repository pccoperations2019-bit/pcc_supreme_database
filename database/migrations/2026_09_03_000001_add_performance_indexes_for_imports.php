<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite index for service_records updateOrCreate WHERE clause
        Schema::table('service_records', function (Blueprint $table) {
            $table->index(
                ['client_name', 'center_id', 'date_conducted', 'service_id', 'source_row_index'],
                'service_records_upsert_index'
            );
        });

        // Index for Service::where('simplified_code', ...) lookups
        Schema::table('services', function (Blueprint $table) {
            $table->index('simplified_code');
        });

        // Index for Project::where('program', ...) lookups
        Schema::table('projects', function (Blueprint $table) {
            $table->index('program');
        });

        // Composite index for Partner::where('center_id')->where('name') lookups
        Schema::table('partners', function (Blueprint $table) {
            $table->index(['center_id', 'name'], 'partners_center_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('service_records', function (Blueprint $table) {
            $table->dropIndex('service_records_upsert_index');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['simplified_code']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['program']);
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropIndex('partners_center_name_index');
        });
    }
};
