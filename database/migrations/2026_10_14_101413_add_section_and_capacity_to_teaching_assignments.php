<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->string('section', 10)->nullable()->after('study_group_id');
            $table->unsignedSmallInteger('capacity')->nullable()->after('section');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->dropColumn(['section', 'capacity']);
        });
    }
};
