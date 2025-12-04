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
        Schema::create('notification_configs', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('email'); // email, sms, webhook
            $table->string('name');
            $table->string('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->nullable()->default(587);
            $table->string('smtp_username')->nullable();
            $table->string('smtp_password')->nullable();
            $table->string('smtp_encryption')->nullable()->default('tls'); // tls, ssl, null
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->jsonb('alert_thresholds')->nullable(); // Configuración de umbrales
            $table->jsonb('recipients')->nullable(); // Lista de destinatarios
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_configs');
    }
};
