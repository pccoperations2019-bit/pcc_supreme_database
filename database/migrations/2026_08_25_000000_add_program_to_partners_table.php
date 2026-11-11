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
        Schema::table('partners', function (Blueprint $table) {
            $table->string('program')->default(ProgramEnum::CBED->value)->comment('Program of the partner, refer to ProgramEnum')->after('center_id');
            $table->foreignId('project_id')
                ->nullable()
                ->after('center_id')
                ->constrained('projects')
                ->nullOnDelete()
                ->comment('Reference to the project associated with the partner');
            $table->index('program');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
            $table->dropColumn('program');
        });
    }
};
