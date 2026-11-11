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
        Schema::table('project_site_processing_staff', function (Blueprint $table) {
            $table->foreignId('project_site_infrastructure_id')
                ->nullable()
                ->after('project_site_id')
                ->constrained(
                    table: 'project_site_infrastructures',
                    indexName: 'project_site_processing_staff_infrastructure_fk',
                )
                ->cascadeOnDelete()
                ->comment('Reference to the processing infrastructure');
        });

        Schema::table('project_site_marketing_staff', function (Blueprint $table) {
            $table->foreignId('project_site_marketing_infrastructure_id')
                ->nullable()
                ->after('project_site_id')
                ->constrained(
                    table: 'project_site_marketing_infrastructures',
                    indexName: 'project_site_marketing_staff_infrastructure_fk',
                )
                ->cascadeOnDelete()
                ->comment('Reference to the marketing infrastructure');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_site_processing_staff', function (Blueprint $table) {
            $table->dropForeign('project_site_processing_staff_infrastructure_fk');
            $table->dropColumn('project_site_infrastructure_id');
        });

        Schema::table('project_site_marketing_staff', function (Blueprint $table) {
            $table->dropForeign('project_site_marketing_staff_infrastructure_fk');
            $table->dropColumn('project_site_marketing_infrastructure_id');
        });
    }
};
