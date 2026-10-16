<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Integrasi multi-aplikasi: klien OAuth dan event webhook mencatat aplikasi pemiliknya (lms, admission).
     * Klien dan event yang sudah ada milik LMS.
     */
    public function up(): void
    {
        Schema::connection(config('passport.connection'))->table('oauth_clients', function (Blueprint $table) {
            $table->string('app', 20)->nullable()->after('name');
        });

        DB::connection(config('passport.connection'))->table('oauth_clients')->where('name', 'like', 'Aksaraloka LMS%')->update(['app' => 'lms']);

        Schema::table('integration_events', function (Blueprint $table) {
            $table->string('app', 20)->default('lms')->after('tenant_id');
            $table->index(['app', 'delivered_at'], 'integration_events_app_delivered_index');
        });
    }

    public function down(): void
    {
        Schema::table('integration_events', function (Blueprint $table) {
            $table->dropIndex('integration_events_app_delivered_index');
            $table->dropColumn('app');
        });

        Schema::connection(config('passport.connection'))->table('oauth_clients', fn (Blueprint $table) => $table->dropColumn('app'));
    }
};
