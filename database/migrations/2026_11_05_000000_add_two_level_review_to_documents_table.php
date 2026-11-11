<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('center_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('center_reviewed_at')->nullable();
            $table->foreignId('agency_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('agency_reviewed_at')->nullable();
            $table->unsignedInteger('submission_version')->default(1);
            $table->unsignedInteger('processing_submission_version')->nullable();
            $table->index(['status', 'submitted_at'], 'documents_review_queue_index');
            $table->index(['agency_reviewed_by', 'agency_reviewed_at'], 'documents_agency_review_index');
        });

        Schema::create('document_import_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('submission_version');
            $table->string('workflow_key', 255);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(
                ['document_id', 'submission_version', 'workflow_key'],
                'document_import_dispatch_unique'
            );
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('document_import_dispatches');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_review_queue_index');
            $table->dropIndex('documents_agency_review_index');
            $table->dropConstrainedForeignId('center_reviewed_by');
            $table->dropColumn('center_reviewed_at');
            $table->dropConstrainedForeignId('agency_reviewed_by');
            $table->dropColumn([
                'agency_reviewed_at',
                'submission_version',
                'processing_submission_version',
            ]);
        });
    }
};
