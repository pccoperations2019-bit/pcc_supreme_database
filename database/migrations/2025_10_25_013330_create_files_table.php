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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('name in the view layer');
            $table->string('path')->comment('Storage path of the file');
            $table->string('type')->comment('MIME type of the file');
            $table->unsignedBigInteger('size')->comment('Size of the file in bytes');
            $table->foreignId('uploaded_by')->constrained('users')->comment('User who uploaded the file');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
