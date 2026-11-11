<?php

use App\Enums\DocumentStatusEnum;
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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade')
                ->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('published_at')->nullable();
            $table->char('status', 20)->default(DocumentStatusEnum::DEFAULT->value);
            $table->string('registration_number')->nullable()->comment('Document Registration Number');
            $table->date('validity')->nullable()->comment('Document Validity');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
