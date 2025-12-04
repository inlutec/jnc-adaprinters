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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consumable_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consumable_reference_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
