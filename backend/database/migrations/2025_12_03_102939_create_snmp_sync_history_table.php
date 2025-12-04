<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snmp_sync_history', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['manual', 'automatic'])->default('manual');
            $table->integer('total_printers')->default(0);
            $table->integer('dispatched')->default(0);
            $table->integer('completed')->default(0);
            $table->integer('failed')->default(0);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snmp_sync_history');
    }
};
