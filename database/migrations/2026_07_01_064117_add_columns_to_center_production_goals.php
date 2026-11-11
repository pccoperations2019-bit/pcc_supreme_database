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
        Schema::table('center_production_goals', function (Blueprint $table) {
            $table->decimal('milk_sales_goal', 15, 2)->nullable()->after('manure_based_goal');
            $table->decimal('meat_sales_goal', 15, 2)->nullable()->after('milk_sales_goal');
            $table->decimal('manure_sales_goal', 15, 2)->nullable()->after('meat_sales_goal');
            $table->decimal('hide_production_goals', 15, 2)->nullable()->after('manure_sales_goal');
            $table->decimal('hide_sales_goals', 15, 2)->nullable()->after('hide_production_goals');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('center_production_goals', function (Blueprint $table) {
            $table->dropColumn('milk_sales_goal');
            $table->dropColumn('meat_sales_goal');
            $table->dropColumn('manure_sales_goal');
            $table->dropColumn('hide_production_goals');
            $table->dropColumn('hide_sales_goals');
        });
    }
};
