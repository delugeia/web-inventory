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
        Schema::table('endpoints', function (Blueprint $table) {
            $table->json('canonical_url_check')->nullable()->after('security_headers');
        });

        Schema::table('endpoint_resolution_run_items', function (Blueprint $table) {
            $table->json('canonical_url_check')->nullable()->after('security_headers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('endpoint_resolution_run_items', function (Blueprint $table) {
            $table->dropColumn('canonical_url_check');
        });

        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropColumn('canonical_url_check');
        });
    }
};
