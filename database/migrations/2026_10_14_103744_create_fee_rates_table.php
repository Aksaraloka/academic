<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->unsignedTinyInteger('ukt_group')->nullable();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->index(['program_id', 'kind', 'ukt_group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_rates');
    }
};
