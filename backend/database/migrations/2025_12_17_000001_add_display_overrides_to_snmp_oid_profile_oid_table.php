<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('snmp_oid_profile_oid', function (Blueprint $table) {
            // Overrides por perfil (un mismo OID puede representar consumibles distintos según el modelo)
            $table->string('display_name')->nullable()->after('is_required');
            $table->string('display_color')->nullable()->after('display_name'); // black/cyan/magenta/yellow o null
            $table->string('display_unit')->nullable()->after('display_color'); // %, pages, etc
            $table->string('display_category')->nullable()->after('display_unit'); // consumable/counter/system/other...
        });
    }

    public function down(): void
    {
        Schema::table('snmp_oid_profile_oid', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'display_color', 'display_unit', 'display_category']);
        });
    }
};


