<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->string('title', 200);
            $table->string('organizer', 150);
            $table->string('role_or_result', 120)->nullable();
            $table->string('level', 16);
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->text('description')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('status', 12)->default('pending');
            $table->unsignedSmallInteger('points')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_activities');
    }
};
