<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thesis_supervisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12)->default('primary');
            $table->timestamps();

            $table->unique(['thesis_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thesis_supervisors');
    }
};
