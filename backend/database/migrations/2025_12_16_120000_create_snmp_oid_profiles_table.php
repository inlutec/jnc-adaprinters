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
        Schema::create('snmp_oid_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('brand')->nullable(); // Marca de impresora (ej: Ricoh, HP, Lexmark)
            $table->string('model')->nullable(); // Modelo específico (ej: IM C4500)
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false); // Perfil por defecto para descubrimiento genérico
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['brand', 'model']);
            $table->index('is_active');
        });

        // Tabla pivot para relación muchos a muchos entre perfiles y OIDs
        Schema::create('snmp_oid_profile_oid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snmp_oid_profile_id')->constrained('snmp_oid_profiles')->onDelete('cascade');
            $table->foreignId('snmp_oid_id')->constrained('snmp_oids')->onDelete('cascade');
            $table->integer('order')->default(0); // Orden de consulta
            $table->boolean('is_required')->default(false); // Si es requerido para el perfil
            $table->timestamps();
            
            $table->unique(['snmp_oid_profile_id', 'snmp_oid_id']);
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snmp_oid_profile_oid');
        Schema::dropIfExists('snmp_oid_profiles');
    }
};

