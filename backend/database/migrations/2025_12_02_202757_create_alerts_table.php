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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->string('severity')->default('medium'); // critical|high|medium|low
            $table->string('status')->default('open'); // open|acknowledged|resolved|dismissed
            $table->string('source')->nullable(); // snmp|inventory|manual
            $table->string('title');
            $table->text('message')->nullable();
            $table->foreignId('printer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consumable_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('acknowledged_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->jsonb('payload')->nullable();
            $table->jsonb('channel_logs')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index(['printer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
