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
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category')->default('umum');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('audience');
            $table->date('opens_on');
            $table->date('closes_on');
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'is_published']);
        });

        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('type');
            $table->string('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('respondent_hash', 64);
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['survey_id', 'respondent_hash']);
        });

        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_question_id')->constrained()->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->json('choices')->nullable();
            $table->timestamps();
            $table->index('survey_question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
    }
};
