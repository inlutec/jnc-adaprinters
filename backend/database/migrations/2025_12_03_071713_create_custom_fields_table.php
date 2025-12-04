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
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type'); // printer, consumable, order
            $table->string('name');
            $table->string('slug');
            $table->string('type'); // text, number, date, select, checkbox, textarea
            $table->jsonb('options')->nullable(); // Para campos select
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->text('help_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['entity_type', 'is_active']);
            $table->unique(['entity_type', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
