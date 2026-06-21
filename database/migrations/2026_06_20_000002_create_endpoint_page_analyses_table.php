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
        Schema::create('endpoint_page_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('endpoint_resolution_run_item_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('page_status', 64)->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('summary', 1024)->nullable();
            $table->json('reasons')->nullable();
            $table->json('signals')->nullable();
            $table->string('model', 128)->nullable();
            $table->string('directive_version', 64)->nullable();
            $table->string('input_hash', 64)->nullable();
            $table->unsignedInteger('input_character_count')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('failure_category', 64)->nullable();
            $table->string('error_message', 1024)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['endpoint_id', 'status'], 'epa_endpoint_status_idx');
            $table->index(['endpoint_id', 'created_at'], 'epa_endpoint_created_idx');
            $table->unique(['endpoint_id', 'input_hash', 'directive_version'], 'epa_endpoint_input_directive_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('endpoint_page_analyses');
    }
};
