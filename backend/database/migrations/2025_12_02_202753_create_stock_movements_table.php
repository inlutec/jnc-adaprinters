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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained()->cascadeOnDelete();
            $table->string('movement_type'); // in|out|adjustment
            $table->integer('quantity');
            $table->string('note')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('performed_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('movement_at')->useCurrent();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['stock_id', 'movement_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
