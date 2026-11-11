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
        Schema::create('manure_productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->nullable()->constrained('centers')->onDelete('set null')->comment('Reference to the center where the project site is located');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->onDelete('set null')->comment('Reference to the partner who owns the project site');
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->onDelete('set null')->comment('Reference to the farmer who owns the project site');
            $table->string('program')->default(ProgramEnum::DEFAULT->value);
            $table->smallInteger('year')->nullable()->comment('Year of the production: 2025');
            $table->tinyInteger('month')->nullable()->comment('Month of the production: 1-12');
            $table->tinyInteger('quarter')->nullable()->comment('Quarter of the production: 1-4');
            $table->decimal('total_volume', 15, 2)->default(0)->comment('Manure Production');
            $table->json('monthly_data')->nullable()->comment('Monthly breakdown data');
            $table->timestamps();

            $table->index('center_id');
            $table->index('partner_id');
            $table->index('farmer_id');

            $table->index('year');
            $table->index('program');
            $table->index('month');
            $table->index(
                ['year', 'program', 'center_id', 'partner_id', 'farmer_id'],
                'mp_lookup_idx'
            );
            $table->index(
                ['center_id', 'program', 'year', 'month'],
                'mp_prod_lookup_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manure_productions');
    }
};
