<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_site_marketing_infrastructures', function (Blueprint $table): void {
            $table->string('partner')->nullable()->after('dairy_box_id');
            $table->string('business_name')->nullable()->after('partner');
            $table->string('manager')->nullable()->after('business_name');
            $table->string('operational_status')->nullable()->after('manager');
            $table->date('launched_date')->nullable()->after('operational_status');
        });
    }

    public function down(): void
    {
        Schema::table('project_site_marketing_infrastructures', function (Blueprint $table): void {
            $table->dropColumn(['partner', 'business_name', 'manager', 'operational_status', 'launched_date']);
        });
    }
};
