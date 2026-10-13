<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('birth_place')->nullable()->after('birth_date');
            $table->string('religion', 30)->nullable()->after('gender');
            $table->string('nik', 16)->nullable()->after('religion');
            $table->string('nationality', 60)->default('Indonesia')->after('nik');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('province', 100)->nullable()->after('city');
            $table->string('postal_code', 10)->nullable()->after('province');
            $table->string('previous_school')->nullable();
            $table->string('admission_path', 50)->nullable();
            $table->unique(['tenant_id', 'nik']);
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->string('occupation', 100)->nullable()->after('relation');
            $table->string('education', 30)->nullable()->after('occupation');
            $table->text('address')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'nik']);
            $table->dropColumn(['birth_place', 'religion', 'nik', 'nationality', 'city', 'province', 'postal_code', 'previous_school', 'admission_path']);
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn(['occupation', 'education', 'address']);
        });
    }
};
