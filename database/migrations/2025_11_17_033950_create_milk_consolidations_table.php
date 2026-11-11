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
        Schema::create('milk_consolidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->index()->constrained('centers')->onDelete('cascade')->comment('Reference to the milk collection center');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->onDelete('set null')->comment('Reference to the partner who supplied the milk');
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->onDelete('set null')->comment('Reference to the farmer who supplied the milk');
            $table->string('supplier_name')->nullable()->comment('Name of the supplier');
            $table->date('period_start')->nullable()->comment('Start date of the consolidation period');
            $table->date('period_end')->nullable()->comment('End date of the consolidation period');
            $table->decimal('total_raw_milk', 14, 3)->default(0)->comment('Total raw milk volume in kilograms');
            $table->decimal('total_from_center', 14, 3)->default(0)->comment('Milk volume from center production');
            $table->decimal('total_from_partners', 14, 3)->default(0)->comment('Milk volume from partners');
            $table->decimal('total_from_farmers', 14, 3)->default(0)->comment('Milk volume from farmers');
            $table->decimal('total_from_suppliers', 14, 3)->default(0)->comment('Milk volume from suppliers');
            $table->decimal('average_buying_price', 14, 4)->nullable()->comment('Average buying price per kilogram');
            $table->decimal('total_cost', 16, 2)->nullable()->comment('Total cost in PHP');
            $table->decimal('average_selling_price', 14, 4)->nullable()->comment('Average selling price per kilogram');
            $table->decimal('revenue_generated', 16, 2)->nullable()->comment('Total revenue generated in PHP');
            $table->decimal('processing_cost', 16, 2)->nullable()->comment('Processing cost in PHP');
            $table->json('metadata')->nullable()->comment('Raw source row, file reference, and computed flags');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->comment('User who created this record');
            $table->timestamps();

            $table->index(['center_id', 'period_start', 'period_end']);
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milk_consolidations');
    }
};
