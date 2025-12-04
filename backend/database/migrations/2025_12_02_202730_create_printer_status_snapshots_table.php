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
        Schema::create('printer_status_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printer_id')->constrained()->cascadeOnDelete();
            $table->string('status')->nullable();
            $table->string('error_code')->nullable();
            $table->unsignedBigInteger('total_pages')->nullable();
            $table->unsignedBigInteger('color_pages')->nullable();
            $table->unsignedBigInteger('bw_pages')->nullable();
            $table->unsignedBigInteger('lifetime_pages')->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->jsonb('consumables')->nullable();
            $table->jsonb('counters')->nullable();
            $table->jsonb('environment')->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->timestamp('captured_at')->useCurrent();
            $table->timestamps();

            $table->index(['printer_id', 'captured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printer_status_snapshots');
    }
};
