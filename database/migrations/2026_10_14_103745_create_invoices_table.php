<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30)->unique();
            $table->string('va_number', 30)->nullable()->index();
            $table->string('title', 150);
            $table->unsignedBigInteger('total');
            $table->string('status', 20)->default('unpaid');
            $table->date('due_on');
            $table->timestamp('issued_at');
            $table->timestamp('paid_at')->nullable();
            $table->boolean('krs_exempt')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'academic_term_id', 'status']);
            $table->index(['student_id', 'academic_term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
