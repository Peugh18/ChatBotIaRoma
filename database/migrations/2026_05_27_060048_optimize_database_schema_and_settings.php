<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Cambiar system_prompt de string a text en bot_settings
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->text('system_prompt')->change();
        });

        // 2. Agregar índices en messages (phone_number, created_at)
        Schema::table('messages', function (Blueprint $table) {
            $table->index('phone_number');
            $table->index('created_at');
        });

        // 3. Agregar índice en conversation_states (requires_human)
        Schema::table('conversation_states', function (Blueprint $table) {
            $table->index('requires_human');
        });
    }

    public function down(): void
    {
        // Revertir índices en conversation_states
        Schema::table('conversation_states', function (Blueprint $table) {
            $table->dropIndex(['requires_human']);
        });

        // Revertir índices en messages
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['phone_number']);
            $table->dropIndex(['created_at']);
        });

        // Revertir system_prompt a string
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->string('system_prompt')->change();
        });
    }
};
