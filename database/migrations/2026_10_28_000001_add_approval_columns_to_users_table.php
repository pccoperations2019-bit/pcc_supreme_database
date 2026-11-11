<?php

use App\Enums\UserStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the account approval columns. `status` gates sign-in, and the
     * approved_by / approved_at pair records the decision. Existing accounts
     * are backfilled to 'active' with approved_at = created_at so that nobody
     * is locked out by this migration.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 32)->default(UserStatusEnum::DEFAULT->value)->after('password');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('status');
        });

        DB::table('users')->update([
            'status' => UserStatusEnum::ACTIVE->value,
            'approved_at' => DB::raw('created_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'approved_at']);
        });
    }
};
