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
        Schema::create('order_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at');
            $table->string('delivery_note_path')->nullable();
            $table->string('delivery_note_mime_type')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_entries');
    }
};
