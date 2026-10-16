<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referensi pendaftaran dari Admission: kunci idempotensi serah terima mahasiswa baru (unik per kampus).
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('admission_ref', 60)->nullable();
            $table->unique(['tenant_id', 'admission_ref'], 'students_tenant_admission_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('students_tenant_admission_ref_unique');
            $table->dropColumn('admission_ref');
        });
    }
};
