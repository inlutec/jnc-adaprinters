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
        Schema::create('consumable_references', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('type')->nullable(); // toner, drum, maintenance-kit, etc.
            $table->string('color')->nullable(); // black, cyan, magenta, yellow
            $table->jsonb('compatible_models')->nullable(); // Modelos de impresoras compatibles
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['brand', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumable_references');
    }
};
