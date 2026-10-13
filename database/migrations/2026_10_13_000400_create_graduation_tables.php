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
        Schema::create('graduation_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('registration_opens_on');
            $table->date('registration_closes_on');
            $table->date('ceremony_on');
            $table->string('venue')->nullable();
            $table->unsignedSmallInteger('quota')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('graduation_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('graduation_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('thesis_title');
            $table->decimal('gpa', 3, 2);
            $table->unsignedSmallInteger('credits_earned');
            $table->date('defense_date');
            $table->string('gown_size', 4)->nullable();
            $table->string('status')->default('diajukan');
            $table->string('rejection_reason')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['graduation_period_id', 'student_id']);
            $table->index(['graduation_period_id', 'status']);
        });

        Schema::create('graduation_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('graduation_period_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->date('issued_on');
            $table->string('chair_name');
            $table->string('chair_title')->default('Ketua Panitia Wisuda');
            $table->string('approver_name');
            $table->string('approver_title')->default('Wakil Rektor Bidang Akademik');
            $table->text('notes')->nullable();
            $table->json('participants')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('graduation_minutes');
        Schema::dropIfExists('graduation_registrations');
        Schema::dropIfExists('graduation_periods');
    }
};
