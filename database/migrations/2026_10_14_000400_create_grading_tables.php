<?php

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
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->date('grading_closes_on')->nullable();
        });

        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->unsignedTinyInteger('weight_assignment')->nullable();
            $table->unsignedTinyInteger('weight_midterm')->nullable();
            $table->unsignedTinyInteger('weight_final')->nullable();
            $table->unsignedTinyInteger('weight_attendance')->nullable();
            $table->timestamp('grades_locked_at')->nullable();
            $table->foreignId('grades_locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('grading_extended_until')->nullable();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->unsignedSmallInteger('legacy_credits')->default(0);
            $table->decimal('legacy_quality_points', 7, 2)->default(0);
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('reguler');
            $table->decimal('assignment_score', 5, 2)->nullable();
            $table->decimal('midterm_score', 5, 2)->nullable();
            $table->decimal('final_exam_score', 5, 2)->nullable();
            $table->decimal('attendance_score', 5, 2)->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->string('letter', 1)->nullable();
            $table->decimal('points', 3, 2)->nullable();
            $table->timestamps();
            $table->unique(['teaching_assignment_id', 'student_id', 'kind']);
            $table->index('student_id');
        });

        Schema::create('grade_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->json('old_scores');
            $table->json('new_scores');
            $table->string('status')->default('menunggu');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_revisions');
        Schema::dropIfExists('grades');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['legacy_credits', 'legacy_quality_points']);
        });

        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grades_locked_by');
            $table->dropColumn(['weight_assignment', 'weight_midterm', 'weight_final', 'weight_attendance', 'grades_locked_at', 'grading_extended_until']);
        });

        Schema::table('academic_terms', function (Blueprint $table) {
            $table->dropColumn('grading_closes_on');
        });
    }
};
