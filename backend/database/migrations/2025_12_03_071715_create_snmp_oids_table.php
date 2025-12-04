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
        Schema::create('snmp_oids', function (Blueprint $table) {
            $table->id();
            $table->string('oid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category'); // consumable, counter, status, environment, system
            $table->string('data_type')->default('string'); // string, integer, gauge, counter
            $table->string('unit')->nullable(); // %, pages, seconds, etc.
            $table->string('color')->nullable(); // Para consumibles: black, cyan, magenta, yellow
            $table->boolean('is_system')->default(false); // No editable si es true
            $table->boolean('is_active')->default(true); // OID activo para consultas
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['category', 'is_system']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snmp_oids');
    }
};
