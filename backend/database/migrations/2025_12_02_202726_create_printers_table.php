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
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('snmp_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('hostname')->nullable();
            $table->ipAddress('ip_address');
            $table->string('mac_address', 32)->nullable();
            $table->string('serial_number')->nullable()->unique();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('status')->default('unknown'); // online|offline|maintenance|error
            $table->boolean('is_color')->default(false);
            $table->boolean('supports_snmp')->default(true);
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('discovery_source')->nullable();
            $table->jsonb('snmp_data')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['ip_address']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
