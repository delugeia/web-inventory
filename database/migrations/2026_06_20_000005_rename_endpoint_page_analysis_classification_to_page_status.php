<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('endpoint_page_analyses', 'classification')
            && ! Schema::hasColumn('endpoint_page_analyses', 'page_status')) {
            Schema::table('endpoint_page_analyses', function (Blueprint $table) {
                $table->renameColumn('classification', 'page_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('endpoint_page_analyses', 'page_status')
            && ! Schema::hasColumn('endpoint_page_analyses', 'classification')) {
            Schema::table('endpoint_page_analyses', function (Blueprint $table) {
                $table->renameColumn('page_status', 'classification');
            });
        }
    }
};
