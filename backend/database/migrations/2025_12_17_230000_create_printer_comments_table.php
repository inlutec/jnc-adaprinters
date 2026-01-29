<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printer_id')->constrained('printers')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['printer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_comments');
    }
};


