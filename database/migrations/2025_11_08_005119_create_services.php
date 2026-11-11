<?php

use App\Enums\ProgramEnum;
use App\Enums\ValueChainSegmentEnum;
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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->char('simplified_code', 32)->nullable();
            $table->char('mfo_code', 32)->nullable();
            $table->string('name');
            $table->char('program', 32)->default(ProgramEnum::DEFAULT->value);
            $table->boolean('is_title')->default(false);

            $table->char('value_chain_segment', 64)->default(ValueChainSegmentEnum::DEFAULT->value);
            $table->json('category')->nullable();
            $table->json('sub_category')->nullable();
            $table->json('type')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('program');
            $table->index(['program', 'name']);
        });

        Schema::create('center_service', function (Blueprint $table) {
            $table->foreignId('center_id')->constrained('centers')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->primary(['center_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
