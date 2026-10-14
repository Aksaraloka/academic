<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('status', 12)->default('submitted');
            $table->string('purpose', 200);
            $table->json('details')->nullable();
            $table->json('snapshot')->nullable();
            $table->string('number', 60)->nullable();
            $table->unsignedSmallInteger('number_year')->nullable();
            $table->unsignedInteger('number_seq')->nullable();
            $table->string('verification_code', 24)->nullable()->unique();
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'type', 'number_year', 'number_seq']);
            $table->index(['tenant_id', 'status']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_requests');
    }
};
