<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thesis_defenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->constrained()->cascadeOnDelete();
            $table->date('scheduled_on');
            $table->string('starts_at', 8);
            $table->string('ends_at', 8);
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chair_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('examiner_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('status', 12)->default('scheduled');
            $table->string('result', 20)->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->date('revision_deadline')->nullable();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['thesis_id', 'scheduled_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thesis_defenses');
    }
};
