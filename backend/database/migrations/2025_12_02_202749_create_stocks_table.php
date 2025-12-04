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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('minimum_quantity')->default(0);
            $table->decimal('average_cost', 12, 2)->nullable();
            $table->foreignId('managed_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['consumable_id', 'site_id', 'department_id'], 'stocks_location_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
