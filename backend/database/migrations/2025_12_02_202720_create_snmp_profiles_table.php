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
        Schema::create('snmp_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('version', 8)->default('v2c'); // v1, v2c, v3
            $table->string('community')->nullable(); // v1/v2
            $table->string('security_level')->nullable(); // noAuthNoPriv|authNoPriv|authPriv
            $table->string('security_username')->nullable();
            $table->string('auth_protocol')->nullable();
            $table->string('auth_password')->nullable();
            $table->string('priv_protocol')->nullable();
            $table->string('priv_password')->nullable();
            $table->string('context_name')->nullable();
            $table->unsignedInteger('port')->default(161);
            $table->unsignedInteger('timeout_ms')->default(1500);
            $table->unsignedTinyInteger('retries')->default(2);
            $table->boolean('is_default')->default(false);
            $table->text('description')->nullable();
            $table->jsonb('oid_map')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snmp_profiles');
    }
};
