<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('meeting_number');
            $table->string('topic', 200);
            $table->text('material')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('attendance_session_id');
            $table->index(['teaching_assignment_id', 'meeting_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_journals');
    }
};
