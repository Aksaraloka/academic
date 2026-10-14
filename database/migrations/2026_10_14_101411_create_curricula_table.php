<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curricula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('batch_from');
            $table->unsignedSmallInteger('batch_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['program_id', 'batch_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curricula');
    }
};
