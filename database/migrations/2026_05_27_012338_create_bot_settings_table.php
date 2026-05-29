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
        Schema::create('bot_settings', function (Blueprint $table) {
            $table->id();
            $table->string('system_prompt')->default('Eres un asistente de ventas amable y profesional de Roma, una tienda de vestidos. Tu objetivo es ayudar a los clientes a encontrar el vestido perfecto y completar su compra.');
            $table->string('yape_number')->default('912874650');
            $table->string('yape_holder')->default('Solange Llantoy');
            $table->text('welcome_message')->default('¡Hola! Soy el asistente de Roma. ¿Qué vestido estás buscando hoy? Puedes enviarme una foto o el nombre del vestido.');
            $table->text('reminder_3min_message')->default('Hermosa nos confirmas si deseas realizar el pedido para poder ayudarte.');
            $table->text('reminder_15min_message')->default('Muchas gracias hermosa, cualquier cosita si te animas más tarde nos escribes. Que tengas un gran día 🤗🤗');
            $table->text('escalation_message')->default('Voy a realizar la consulta a un agente especializado y en breve le brindamos una respuesta.');
            $table->boolean('auto_reply_enabled')->default(true);
            $table->string('groq_api_key')->nullable();
            $table->string('model_chat')->default('llama-3.3-70b-versatile');
            $table->string('model_vision')->default('meta-llama/llama-4-scout-17b-16e-instruct');
            $table->integer('reminder_3min_seconds')->default(180);
            $table->integer('reminder_15min_seconds')->default(900);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_settings');
    }
};
