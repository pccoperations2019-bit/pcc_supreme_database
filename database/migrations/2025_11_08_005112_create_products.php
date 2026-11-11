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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->char('code', 64)->unique();
            $table->string('category')->nullable();
            $table->string('sub_category')->nullable();
            $table->string('product_registration')->nullable()->comment('Registration of Product');
            $table->string('name');
            $table->char('size', 32)->nullable();
            $table->decimal('cost_per_qty', 10, 2)->default(0)->comment('Product Selling Price');
            $table->text('details')->nullable();
            $table->timestamps();
        });

        Schema::create('center_product', function (Blueprint $table) {
            $table->foreignId('center_id')->constrained('centers')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->primary(['center_id', 'product_id']);
        });

        Schema::create('partner_product', function (Blueprint $table) {
            $table->foreignId('partner_id')->constrained('partners')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->primary(['partner_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('center_product');
        Schema::dropIfExists('products');
    }
};
