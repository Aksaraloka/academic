<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['enrollment_id', 'teaching_assignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_items');
    }
};
