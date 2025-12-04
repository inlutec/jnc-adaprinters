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
        Schema::create('printer_print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('snapshot_id')
                ->nullable()
                ->constrained('printer_status_snapshots')
                ->nullOnDelete();
            $table->unsignedBigInteger('start_counter')->nullable();
            $table->unsignedBigInteger('end_counter')->nullable();
            $table->unsignedBigInteger('total_prints')->default(0);
            $table->unsignedBigInteger('color_prints')->default(0);
            $table->unsignedBigInteger('bw_prints')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('source')->default('snmp'); // snmp|manual|import
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['printer_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printer_print_logs');
    }
};
