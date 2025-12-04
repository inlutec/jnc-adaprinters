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
        Schema::table('users', function (Blueprint $table) {
            // Permisos de visualización de páginas (menús del sidebar)
            $table->json('page_permissions')->nullable()->after('password');
            // Permisos de visualización de ubicaciones (provincias, sedes, departamentos)
            $table->json('location_permissions')->nullable()->after('page_permissions');
            // Permisos de lectura/escritura por módulo
            $table->json('read_write_permissions')->nullable()->after('location_permissions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['page_permissions', 'location_permissions', 'read_write_permissions']);
        });
    }
};
