<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom address, city, province, dan postal_code pada students adalah alamat sesuai KTP.
     * Kolom domicile_* menyimpan tempat tinggal saat ini (mis. kos bagi mahasiswa perantau).
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('is_domicile_same')->default(true);
            $table->string('residence_type', 40)->nullable();
            $table->text('domicile_address')->nullable();
            $table->string('domicile_city', 100)->nullable();
            $table->string('domicile_province', 100)->nullable();
            $table->string('domicile_postal_code', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['is_domicile_same', 'residence_type', 'domicile_address', 'domicile_city', 'domicile_province', 'domicile_postal_code']);
        });
    }
};
