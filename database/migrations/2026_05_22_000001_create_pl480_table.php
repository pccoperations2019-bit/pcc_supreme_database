<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pl480', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->nullable()->constrained()->comment('Reference to the center associated with the PL480 record');
            $table->foreignId('partner_id')->nullable()->constrained()->comment('Reference to the farmer or partner associated with the PL480 record');
            $table->string('province');
            $table->unsignedSmallInteger('year');
            $table->decimal('value', 18, 2)->default(0);

            $table->timestamps();

            $table->index(['center_id', 'year'], 'pl480_center_year_index');
            $table->index(['partner_id', 'year'], 'pl480_partner_year_index');
            $table->index(['year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pl480');
    }
};
