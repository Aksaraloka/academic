<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akreditasi prodi (mis. "Unggul", "B"), ditampilkan di halaman publik Admission.
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('accreditation', 30)->nullable()->after('degree');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('accreditation');
        });
    }
};
