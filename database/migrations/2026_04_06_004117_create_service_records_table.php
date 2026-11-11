<?php

use App\Enums\ServiceProgramEnum;
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
        Schema::create('service_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->nullable()->constrained('centers')->onDelete('cascade');
            $table->foreignId('service_id')->nullable()->constrained('services')->onDelete('cascade');
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->onDelete('cascade');
            $table->date('month_submitted')->nullable();
            $table->string('client_name');
            $table->char('program', 32)->default(ServiceProgramEnum::DEFAULT->value);
            $table->string('gender', 16)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('cascade')->comment('Reference to the project associated with the service record');
            $table->integer('no_animals_served')->default(0);
            $table->date('date_conducted')->nullable();
            $table->unsignedInteger('source_row_index')->nullable()->index();
            $table->timestamps();

            $table->index('center_id');
            $table->index('service_id');
            $table->index('farmer_id');
            $table->index('client_name');
            $table->index('program');
            $table->index(['month_submitted', 'date_conducted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_records');
    }
};
