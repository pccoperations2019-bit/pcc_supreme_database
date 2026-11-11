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
        Schema::table('project_site_equipment', function (Blueprint $table) {
            $table->integer('quantity')->nullable()->after('supplier_name')->comment('Quantity of the equipment');
            $table->string('capacity')->nullable()->after('quantity')->comment('Capacity of the equipment (e.g. 150 Liters)');
            $table->decimal('unit_cost', 15, 2)->nullable()->after('name_of_equipment')->comment('Unit cost of the equipment');
            $table->decimal('total_cost', 15, 2)->nullable()->after('unit_cost')->comment('Total cost of the equipment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_site_equipment', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'capacity', 'unit_cost', 'total_cost']);
        });
    }
};
