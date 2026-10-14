<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thesis_guidance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->date('held_on');
            $table->string('topic', 200);
            $table->text('summary');
            $table->text('next_steps')->nullable();
            $table->string('status', 12)->default('pending');
            $table->text('feedback')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['thesis_id', 'held_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thesis_guidance_logs');
    }
};
