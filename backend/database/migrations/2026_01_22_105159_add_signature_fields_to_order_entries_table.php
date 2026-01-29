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
        Schema::table('order_entries', function (Blueprint $table) {
            $table->string('signature_token')->nullable()->unique()->after('delivery_note_mime_type');
            $table->string('signature_email')->nullable()->after('signature_token');
            $table->timestamp('signature_sent_at')->nullable()->after('signature_email');
            $table->text('signature_data')->nullable()->after('signature_sent_at'); // Para almacenar la firma/rúbrica
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_entries', function (Blueprint $table) {
            $table->dropColumn(['signature_token', 'signature_email', 'signature_sent_at', 'signature_data']);
        });
    }
};
