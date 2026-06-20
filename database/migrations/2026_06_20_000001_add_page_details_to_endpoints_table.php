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
            $table->string('page_title', 512)->nullable()->after('resolved_url');
            $table->longText('page_content')->nullable()->after('page_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropColumn([
                'page_title',
                'page_content',
            ]);
        });
    }
};
