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
        Schema::table('addresses', function (Blueprint $table) {
            if (! Schema::hasIndex('addresses', 'addresses_region_index')) {
                $table->index(['region'], 'addresses_region_index');
            }
            if (! Schema::hasIndex('addresses', 'addresses_province_index')) {
                $table->index(['province'], 'addresses_province_index');
            }
            if (! Schema::hasIndex('addresses', 'addresses_city_index')) {
                $table->index(['city'], 'addresses_city_index');
            }
            if (! Schema::hasIndex('addresses', 'addresses_district_index')) {
                $table->index(['district'], 'addresses_district_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndexIfExists('addresses_region_index');
            $table->dropIndexIfExists('addresses_province_index');
            $table->dropIndexIfExists('addresses_city_index');
            $table->dropIndexIfExists('addresses_district_index');
        });
    }
};
