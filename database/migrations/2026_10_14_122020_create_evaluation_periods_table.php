<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->date('opens_on');
            $table->date('closes_on');
            $table->boolean('gates_grades')->default(true);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique('academic_term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_periods');
    }
};
