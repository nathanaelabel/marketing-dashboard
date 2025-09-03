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
        Schema::create('branch_targets', function (Blueprint $table) {
            $table->id();
            $table->integer('month'); // 1-12
            $table->integer('year');
            $table->string('category'); // MIKA, SPARE PART
            $table->string('branch_name');
            $table->decimal('target_amount', 15, 2);
            $table->timestamps();
            
            // Unique constraint to prevent duplicate targets for same month/year/category/branch
            $table->unique(['month', 'year', 'category', 'branch_name'], 'unique_branch_target');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_targets');
    }
};
