<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advising_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 150);
            $table->text('notes');
            $table->text('follow_up')->nullable();
            $table->date('held_on');
            $table->timestamps();

            $table->index(['student_id', 'held_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advising_notes');
    }
};
