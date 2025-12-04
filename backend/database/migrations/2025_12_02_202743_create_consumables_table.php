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
        Schema::create('consumables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->string('type')->nullable(); // toner|drum|maintenance-kit...
            $table->string('brand')->nullable();
            $table->string('color')->nullable(); // black|cyan|magenta|yellow|...
            $table->boolean('is_color')->default(false);
            $table->unsignedInteger('average_yield')->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->jsonb('compatible_models')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumables');
    }
};
