<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snmp_sync_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insertar configuración por defecto
        DB::table('snmp_sync_configs')->insert([
            'key' => 'auto_sync_enabled',
            'value' => 'true',
            'description' => 'Habilitar sincronización automática',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('snmp_sync_configs')->insert([
            'key' => 'auto_sync_frequency',
            'value' => '15',
            'description' => 'Frecuencia de sincronización automática en minutos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('snmp_sync_configs');
    }
};
