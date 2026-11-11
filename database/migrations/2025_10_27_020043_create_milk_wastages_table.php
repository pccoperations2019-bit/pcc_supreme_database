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
        Schema::create('milk_wastages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milk_production_id')->constrained()->cascadeOnDelete()->comment('Reference to the related milk production record');
            $table->decimal('milk_spoiled_volume', 10, 2)->default(0)->comment('Total volume of spoiled milk in kilograms');
            $table->decimal('milk_spoiled_cost', 10, 2)->default(0)->comment('Total cost associated with the spoiled milk');
            $table->decimal('milk_spoiled_price', 10, 2)->default(0)->comment('Estimated value or selling price of the spoiled milk');
            $table->text('reason')->nullable()->comment('Reason or explanation for milk spoilage');
            $table->timestamps();

            $table->index('milk_production_id', 'milk_wastages_milk_production_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milk_wastages');
    }
};
