<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->date('graduated_on')->nullable()->after('entry_date');
            $table->string('graduation_predicate', 40)->nullable()->after('graduated_on');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['graduated_on', 'graduation_predicate']);
        });
    }
};
