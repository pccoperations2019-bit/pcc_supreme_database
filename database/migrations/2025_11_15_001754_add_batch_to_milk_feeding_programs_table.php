<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milk_feeding_programs', function (Blueprint $table) {
            $table->integer('batch')->nullable()->after('feeding_days')->comment('Batch identifier for the feeding program');
            $table->index('batch', 'milk_feeding_programs_batch_index');
        });
    }

    public function down(): void
    {
        Schema::table('milk_feeding_programs', function (Blueprint $table) {
            $table->dropIndex('milk_feeding_programs_batch_index');
            $table->dropColumn('batch');
        });
    }
};
