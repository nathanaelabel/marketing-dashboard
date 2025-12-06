<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('c_invoiceline', function (Blueprint $table) {
            $table->integer('c_orderline_id')->nullable()->after('m_inoutline_id');
            $table->index('c_orderline_id', 'idx_c_invoiceline_c_orderline_id');
        });
    }

    public function down(): void
    {
        Schema::table('c_invoiceline', function (Blueprint $table) {
            $table->dropIndex('idx_c_invoiceline_c_orderline_id');
            $table->dropColumn('c_orderline_id');
        });
    }
};
