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
        Schema::create('beneficiary_sources', function (Blueprint $table) {
            $table->id();
            $table->char('code', 12)->comment('Funding source code (e.g., depEd, dswd)');
            $table->string('name')->comment('Name of the funding source');
            $table->unsignedInteger('amount')->default(0)->comment('Total number of beneficiaries');
            $table->year('year');
            $table->timestamps();

            $table->index(['code', 'year'], 'beneficiary_sources_code_year_index');
            $table->index('year', 'beneficiary_sources_year_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beneficiary_sources');
    }
};
