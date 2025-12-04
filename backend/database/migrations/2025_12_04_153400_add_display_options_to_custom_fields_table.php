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
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->boolean('show_in_table')->default(false)->after('is_active');
            $table->unsignedInteger('table_order')->default(0)->after('show_in_table');
            $table->boolean('show_in_creation_wizard')->default(false)->after('table_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropColumn(['show_in_table', 'table_order', 'show_in_creation_wizard']);
        });
    }
};

