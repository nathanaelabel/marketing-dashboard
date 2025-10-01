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
        Schema::table('c_invoice', function (Blueprint $table) {
            $table->decimal('totallines', 16, 2)->after('dateinvoiced');
            $table->integer('c_bpartner_id')->after('ad_org_id');

            // Foreign key to c_bpartner removed due to cross-branch references in multi-source sync
            // $table->foreign('c_bpartner_id')->references('c_bpartner_id')->on('c_bpartner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('c_invoice', function (Blueprint $table) {
            // $table->dropForeign(['c_bpartner_id']); // Foreign key not created
            $table->dropColumn(['totallines', 'c_bpartner_id']);
        });
    }
};
