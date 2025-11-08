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
        Schema::create('sync_progress', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->index(); // Unique ID untuk setiap run sync-all
            $table->string('connection_name')->index();
            $table->string('table_name')->index();
            $table->string('model_name');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'skipped'])->default('pending');
            $table->integer('records_processed')->nullable();
            $table->integer('records_skipped')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            // Composite index untuk query performance
            $table->index(['batch_id', 'status']);
            $table->index(['batch_id', 'connection_name']);
        });

        Schema::create('sync_batches', function (Blueprint $table) {
            $table->string('batch_id')->primary();
            $table->enum('status', ['running', 'completed', 'failed', 'interrupted'])->default('running');
            $table->integer('total_tables')->default(0);
            $table->integer('completed_tables')->default(0);
            $table->integer('failed_tables')->default(0);
            $table->text('command_options')->nullable(); // JSON untuk menyimpan options
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_progress');
        Schema::dropIfExists('sync_batches');
    }
};
