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
        Schema::table('centers', function (Blueprint $table) {
            $table->json('animal_inventory')->nullable()->comment('Animal inventory rows (classification, count, breed, remarks)');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->json('animal_inventory')->nullable()->comment('Animal inventory rows (classification, count, breed, remarks)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('centers', function (Blueprint $table) {
            $table->dropColumn('animal_inventory');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('animal_inventory');
        });
    }
};
