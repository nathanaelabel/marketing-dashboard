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
        Schema::table('m_inoutline', function (Blueprint $table) {
            $table->bigInteger('m_locator_id')->nullable()->after('m_product_id');

            // Add index
            $table->index('m_locator_id');

            // Add foreign key
            $table->foreign('m_locator_id')
                ->references('m_locator_id')
                ->on('m_locator')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_inoutline', function (Blueprint $table) {
            $table->dropForeign(['m_locator_id']);
            $table->dropIndex(['m_locator_id']);
            $table->dropColumn('m_locator_id');
        });
    }
};
