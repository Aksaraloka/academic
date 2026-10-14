<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('provider', 150);
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->unsignedBigInteger('amount_per_term')->nullable();
            $table->unsignedSmallInteger('quota')->nullable();
            $table->decimal('min_gpa', 3, 2)->nullable();
            $table->unsignedTinyInteger('min_semester')->nullable();
            $table->date('opens_on');
            $table->date('closes_on');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarships');
    }
};
