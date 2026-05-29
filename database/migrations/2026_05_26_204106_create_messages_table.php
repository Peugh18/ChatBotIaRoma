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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique(); // ID del mensaje de WhatsApp
            $table->string('phone_number'); // Número de teléfono del cliente
            $table->string('customer_name')->nullable(); // Nombre del cliente (si está disponible)
            $table->text('content'); // Contenido del mensaje
            $table->enum('direction', ['incoming', 'outgoing']); // Entrante o saliente
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->timestamp('whatsapp_timestamp')->nullable(); // Timestamp de WhatsApp
            $table->json('metadata')->nullable(); // Metadatos adicionales
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
