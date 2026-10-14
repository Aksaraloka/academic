<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('partner_name', 150);
            $table->string('position', 120)->nullable();
            $table->string('location', 120)->nullable();
            $table->string('field_supervisor', 120)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('proposed_credits')->default(0);
            $table->string('status', 12)->default('submitted');
            $table->foreignId('supervisor_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('final_letter', 2)->nullable();
            $table->text('evaluation_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internships');
    }
};
