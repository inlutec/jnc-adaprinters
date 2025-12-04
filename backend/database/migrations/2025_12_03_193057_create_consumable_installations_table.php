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
        Schema::create('consumable_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printer_id')->constrained()->onDelete('cascade');
            $table->foreignId('stock_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('quantity')->default(1);
            $table->text('observations')->nullable();
            $table->foreignId('installed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('installed_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumable_installations');
    }
};
