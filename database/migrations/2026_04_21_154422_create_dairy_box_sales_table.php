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
        Schema::create('dairy_box_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dairy_box_id')->nullable()->constrained()->onDelete('set null')->comment('Associated dairy box');
            $table->foreignId('center_id')->nullable()->constrained()->onDelete('set null')->comment('Associated center');
            $table->foreignId('partner_id')->nullable()->constrained()->onDelete('set null')->comment('Associated partner');
            $table->decimal('total_sales')->default(0)->comment('Dairy box Sales');
            $table->integer('year')->nullable()->comment('Year of sales');
            $table->tinyInteger('month')->nullable()->comment('Month of the sales: 1-12');
            $table->tinyInteger('quarter')->nullable()->comment('Quarter of the sales: 1-4');
            $table->tinyInteger('week')->nullable()->comment('Week of the sales: 1-53');
            $table->date('period_start')->nullable()->comment('Sales period start date');
            $table->date('period_end')->nullable()->comment('Sales period end date');
            $table->timestamps();

            $table->index('dairy_box_id', 'idx_dairy_box_sales_dairy_box_id');
            $table->index('center_id', 'idx_dairy_box_sales_center_id');
            $table->index('partner_id', 'idx_dairy_box_sales_partner_id');
            $table->index(['period_start', 'period_end'], 'idx_dairy_box_sales_period');
            $table->index(['dairy_box_id', 'period_start'], 'idx_dairy_box_sales_box_period');
            $table->index(['center_id', 'period_start'], 'idx_dairy_box_sales_center_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dairy_box_sales');
    }
};
