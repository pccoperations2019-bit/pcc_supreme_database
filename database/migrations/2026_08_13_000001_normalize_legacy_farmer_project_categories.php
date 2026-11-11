<?php

use App\Enums\ProjectProgramEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (ProjectProgramEnum::matches() as $value => $variants) {
            DB::table('farmers')
                ->whereIn('project_category', $variants)
                ->where('project_category', '!=', $value)
                ->update(['project_category' => $value]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Legacy values are normalized in up(); restoring original spellings is not possible.
    }
};
