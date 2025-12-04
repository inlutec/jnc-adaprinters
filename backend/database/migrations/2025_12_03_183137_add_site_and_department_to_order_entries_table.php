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
        Schema::table('order_entries', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('order_id')->constrained()->onDelete('set null');
            $table->foreignId('department_id')->nullable()->after('site_id')->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_entries', function (Blueprint $table) {
            //
        });
    }
};
