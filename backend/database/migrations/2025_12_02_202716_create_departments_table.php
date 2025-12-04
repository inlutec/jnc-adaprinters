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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnUpdate();
            $table->string('name');
            $table->string('code', 25)->nullable()->unique();
            $table->string('floor')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
