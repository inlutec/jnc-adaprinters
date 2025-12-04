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
        Schema::table('printer_print_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('color_counter_total')->nullable()->after('end_counter');
            $table->unsignedBigInteger('bw_counter_total')->nullable()->after('color_counter_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('printer_print_logs', function (Blueprint $table) {
            $table->dropColumn(['color_counter_total', 'bw_counter_total']);
        });
    }
};
