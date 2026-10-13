<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Presensi hanya dicatat oleh dosen, jadi token QR untuk absen mandiri mahasiswa dihapus.
     */
    public function up(): void
    {
        Schema::dropIfExists('qr_tokens');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->index();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }
};
