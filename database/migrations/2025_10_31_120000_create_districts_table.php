<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('psgc.tables.districts', 'districts'), function (Blueprint $table) {
            $table->string('code');
            $table->string('name');
            $table->string('region_code')->nullable()->index();
            $table->string('province_code')->nullable()->index();
            // One district row per city: a legislative-district `code` is shared
            // by every city/municipality in that district, so `city_code` is the
            // natural unique key (the DistrictController looks up by city_code).
            $table->string('city_code')->primary();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('psgc.tables.districts', 'districts'));
    }
};
