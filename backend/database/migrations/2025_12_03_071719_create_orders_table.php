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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('printer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consumable_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending'); // pending, sent, received, cancelled
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->string('email_to')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable(); // Para campos personalizados
            $table->foreignId('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
            $table->index(['printer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
