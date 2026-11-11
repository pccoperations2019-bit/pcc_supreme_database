<?php

use App\Enums\ProgramEnum;
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
            $table->string('program')->default(ProgramEnum::DEFAULT->value)->after('year');
            $table->unique(['center_id', 'year', 'program'], 'center_production_goals_center_year_program_unique');
            $table->dropIndex('center_production_goals_center_year_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('center_production_goals', function (Blueprint $table) {
            $table->index(['center_id', 'year'], 'center_production_goals_center_year_index');
            $table->dropUnique('center_production_goals_center_year_program_unique');
            $table->dropColumn('program');
        });
    }
};
