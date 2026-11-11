<?php

use App\Enums\AddressCategoryEnum;
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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->nullable()->constrained()->onDelete('cascade')->comment('Foreign key to centers table');
            $table->char('category', 32)->default(AddressCategoryEnum::DEFAULT->value)->comment('Category of the address, e.g., home, office, billing');
            $table->morphs('addressable');
            $table->string('street')->nullable()->comment('Street address');
            $table->string('barangay')->nullable()->comment('Barangay or local district');
            $table->string('city')->nullable()->comment('City or municipality');
            $table->string('province')->nullable()->comment('Province or state');
            $table->string('region')->nullable()->comment('Administrative region');
            $table->string('district')->nullable()->comment('District (if applicable)');
            $table->unsignedInteger('zip_code')->nullable()->comment('Postal or ZIP code (4-digit numeric) - PH');
            $table->string('country')->default('Philippines')->comment('Country, default is Philippines');
            $table->decimal('latitude', 10, 7)->nullable()->comment('Latitude coordinate');
            $table->decimal('longitude', 10, 7)->nullable()->comment('Longitude coordinate');
            $table->string('marker_icon')->nullable()->comment('Optional marker icon for maps');
            $table->index(['addressable_id', 'addressable_type'], 'addressable_index');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
