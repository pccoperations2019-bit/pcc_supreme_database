<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centers', function (Blueprint $table) {
            if (! Schema::hasColumn('centers', 'date_assisted')) {
                $table->date('date_assisted')->nullable()->after('metadata')->comment('Date when center was assisted');
            }
        });

        Schema::table('partners', function (Blueprint $table) {
            if (! Schema::hasColumn('partners', 'date_assisted')) {
                $table->date('date_assisted')->nullable()->after('metadata')->comment('Date when partner was assisted');
            }
        });

        Schema::table('farmers', function (Blueprint $table) {
            if (! Schema::hasColumn('farmers', 'date_assisted')) {
                $table->date('date_assisted')->nullable()->after('is_assisted')->comment('Date when farmer was assisted');
            }
        });
    }

    public function down(): void
    {
        Schema::table('centers', function (Blueprint $table) {
            if (Schema::hasColumn('centers', 'date_assisted')) {
                $table->dropColumn('date_assisted');
            }
        });

        Schema::table('partners', function (Blueprint $table) {
            if (Schema::hasColumn('partners', 'date_assisted')) {
                $table->dropColumn('date_assisted');
            }
        });

        Schema::table('farmers', function (Blueprint $table) {
            if (Schema::hasColumn('farmers', 'date_assisted')) {
                $table->dropColumn('date_assisted');
            }
        });
    }
};
