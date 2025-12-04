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
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type'); // printer, consumable, order
            $table->unsignedBigInteger('entity_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->unique(['custom_field_id', 'entity_type', 'entity_id'], 'custom_field_value_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
