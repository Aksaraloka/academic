<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_conversion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_conversion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('credits');
            $table->string('letter', 2)->nullable();
            $table->decimal('points', 3, 1)->nullable();
            $table->timestamps();

            $table->unique(['credit_conversion_id', 'subject_id']);
            $table->index('subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_conversion_items');
    }
};
