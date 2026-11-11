<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // `pending_center_review` and `pending_agency_review` are 21 chars;
            // the original column was char(20), which caused uploads to fail.
            $table->char('status', 32)->default('draft')->change();
        });
    }

    public function down(): void
    {
        // Keep the wider column on rollback so existing two-level statuses are
        // not truncated. Reverting the workflow code does not make stored values
        // safe to narrow again.
    }
};
