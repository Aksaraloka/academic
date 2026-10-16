<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outbox webhook ke LMS: event disimpan dalam transaksi yang sama dengan perubahannya lalu dikirim oleh antrean.
     */
    public function up(): void
    {
        Schema::create('integration_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->json('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'delivered_at'], 'integration_events_tenant_delivered_index');
            $table->index(['type', 'created_at'], 'integration_events_type_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_events');
    }
};
