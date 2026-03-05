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
            $table->boolean('redirect_followed')->default(false)->after('failure_reason');
            $table->unsignedSmallInteger('redirect_count')->default(0)->after('redirect_followed');
            $table->json('redirect_chain')->nullable()->after('redirect_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropColumn([
                'redirect_followed',
                'redirect_count',
                'redirect_chain',
            ]);
        });
    }
};
