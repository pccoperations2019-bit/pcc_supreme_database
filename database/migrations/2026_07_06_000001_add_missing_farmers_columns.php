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
        Schema::table('farmers', function (Blueprint $table) {
            // Assets tab
            $table->string('pcc_projects_assets')->nullable()->comment('PCC Projects Assets');

            // Poverty Probability Index - stored as metadata, no columns needed per user request

            // Farm Information
            $table->string('farm_name')->nullable()->comment('Farm Name');
            $table->string('farm_region')->nullable()->comment('Farm Region');
            $table->string('farm_province')->nullable()->comment('Farm Province');
            $table->string('farm_municipality_city')->nullable()->comment('Farm Municipality/City');
            $table->string('farm_barangay')->nullable()->comment('Farm Barangay');
            $table->string('farm_purok_zone')->nullable()->comment('Farm Purok/Zone');

            // Manure Management - missing methods
            $table->boolean('is_solid_storage_covered')->nullable()->comment('Solid Storage covered');
            $table->boolean('is_solid_storage_additives')->nullable()->comment('Solid Storage additives');
            $table->boolean('is_uncovered_anaerobic_lagoon')->nullable()->comment('Uncovered Anaerobic Lagoon');

            // Other Livestock - split into label and count
            $table->renameColumn('other_livestock', 'other_livestock_count');
            $table->string('other_livestock_label')->nullable()->comment('Other livestock type label');
            $table->json('other_livestock_details')->nullable()->comment('Other livestock details');

            // Entrepreneur & Enterprise Images
            $table->text('entrepreneur_image')->nullable()->comment('Image of entrepreneur');
            $table->text('enterprise_image')->nullable()->comment('Image of the enterprise');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropColumn([
                'pcc_projects_assets',
                'farm_name',
                'farm_region',
                'farm_province',
                'farm_municipality_city',
                'farm_barangay',
                'farm_purok_zone',
                'is_solid_storage_covered',
                'is_solid_storage_additives',
                'is_uncovered_anaerobic_lagoon',
                'other_livestock_label',
                'other_livestock_details',
                'entrepreneur_image',
                'enterprise_image',
            ]);

            $table->renameColumn('other_livestock_count', 'other_livestock');
        });
    }
};
