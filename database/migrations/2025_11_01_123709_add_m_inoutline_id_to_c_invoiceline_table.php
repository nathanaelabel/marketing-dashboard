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
        Schema::table('c_invoiceline', function (Blueprint $table) {
            $table->bigInteger('m_inoutline_id')->nullable()->after('m_product_id');

            // Add foreign key
            $table->foreign('m_inoutline_id')
                ->references('m_inoutline_id')
                ->on('m_inoutline')
                ->onDelete('set null');

            // Add index
            $table->index('m_inoutline_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('c_invoiceline', function (Blueprint $table) {
            $table->dropForeign(['m_inoutline_id']);
            $table->dropIndex(['m_inoutline_id']);
            $table->dropColumn('m_inoutline_id');
        });
    }
};
