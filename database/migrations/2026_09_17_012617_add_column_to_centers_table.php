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
            $table->char('program', 32)
                ->nullable()
                ->after('category_size')
                ->comment('Program category or type for the center (IH, CBED)');

            $table->unsignedBigInteger('project_id')
                ->nullable()
                ->after('program')
                ->comment('Foreign key reference to projects table');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('centers', function (Blueprint $table) {
            $table->dropColumn([
                'program',
                'project_id',
            ]);
        });
    }
};
