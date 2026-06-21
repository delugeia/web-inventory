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
            $table->string('rendered_capture_status', 32)->nullable()->after('page_content');
            $table->string('rendered_page_title', 512)->nullable()->after('rendered_capture_status');
            $table->longText('rendered_page_text')->nullable()->after('rendered_page_title');
            $table->longText('rendered_page_content')->nullable()->after('rendered_page_text');
            $table->unsignedInteger('rendered_capture_duration_ms')->nullable()->after('rendered_page_content');
            $table->string('rendered_capture_error', 1024)->nullable()->after('rendered_capture_duration_ms');
            $table->timestamp('rendered_captured_at')->nullable()->after('rendered_capture_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropColumn([
                'rendered_capture_status',
                'rendered_page_title',
                'rendered_page_text',
                'rendered_page_content',
                'rendered_capture_duration_ms',
                'rendered_capture_error',
                'rendered_captured_at',
            ]);
        });
    }
};
