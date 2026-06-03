<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bot_settings', 'huggingface_token')) {
            if (! Schema::hasColumn('bot_settings', 'jina_api_key')) {
                Schema::table('bot_settings', function (Blueprint $table) {
                    $table->string('jina_api_key')->nullable()->comment('Jina API key for catalog image embeddings');
                });
            }

            return;
        }

        Schema::table('bot_settings', function (Blueprint $table) {
            $table->renameColumn('huggingface_token', 'jina_api_key');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bot_settings', 'jina_api_key') && ! Schema::hasColumn('bot_settings', 'huggingface_token')) {
            Schema::table('bot_settings', function (Blueprint $table) {
                $table->renameColumn('jina_api_key', 'huggingface_token');
            });
        }
    }
};
