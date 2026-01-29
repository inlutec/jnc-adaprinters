<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->foreignId('snmp_oid_profile_id')
                ->nullable()
                ->after('snmp_profile_id')
                ->constrained('snmp_oid_profiles')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropForeign(['snmp_oid_profile_id']);
            $table->dropColumn('snmp_oid_profile_id');
        });
    }
};

