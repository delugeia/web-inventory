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
        Schema::create('endpoint_resolution_run_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('endpoint_resolution_run_id');
            $table->foreignId('endpoint_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position');
            $table->string('location', 2048);
            $table->string('status', 32)->default('queued');
            $table->string('resolved_url', 2048)->nullable();
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->string('failure_reason', 1024)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->foreign('endpoint_resolution_run_id', 'erri_run_fk')
                ->references('id')
                ->on('endpoint_resolution_runs')
                ->cascadeOnDelete();
            $table->unique(['endpoint_resolution_run_id', 'position'], 'erri_run_position_unique');
            $table->index(['endpoint_resolution_run_id', 'status'], 'erri_run_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('endpoint_resolution_run_items');
    }
};
