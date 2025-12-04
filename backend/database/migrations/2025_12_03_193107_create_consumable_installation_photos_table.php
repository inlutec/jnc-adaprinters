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
        Schema::create('consumable_installation_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumable_installation_id')->constrained()->onDelete('cascade');
            $table->string('photo_path');
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumable_installation_photos');
    }
};
