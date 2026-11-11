<?php

use App\Enums\ProgramEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained()->onDelete('cascade');
            $table->string('program', 16)->default(ProgramEnum::DEFAULT->value);
            $table->string('product_type', 32);
            $table->string('task_type', 32);
            $table->integer('year');
            $table->integer('month');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_no_data')->default(false);
            $table->timestamp('no_data_at')->nullable();
            $table->foreignId('no_data_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('review_status', 16)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('return_reason')->nullable();
            $table->text('review_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['center_id', 'program', 'product_type', 'task_type', 'year', 'month'], 'monthly_tasks_unique');
            $table->index(['center_id', 'program', 'year', 'month'], 'monthly_tasks_period_idx');
            $table->index(['is_completed', 'year', 'month'], 'monthly_tasks_status_idx');
            $table->index(['is_no_data', 'year', 'month'], 'monthly_tasks_no_data_idx');
            $table->index(['review_status', 'year', 'month'], 'monthly_tasks_review_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_tasks');
    }
};
