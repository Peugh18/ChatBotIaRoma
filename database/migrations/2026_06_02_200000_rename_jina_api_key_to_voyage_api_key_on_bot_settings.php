<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bot_settings', 'jina_api_key')) {
            Schema::table('bot_settings', function (Blueprint $table) {
                $table->renameColumn('jina_api_key', 'voyage_api_key');
            });

            return;
        }

        if (! Schema::hasColumn('bot_settings', 'voyage_api_key')) {
            Schema::table('bot_settings', function (Blueprint $table) {
                $table->string('voyage_api_key')->nullable()->comment('Voyage AI API key for catalog image embeddings');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bot_settings', 'voyage_api_key') && ! Schema::hasColumn('bot_settings', 'jina_api_key')) {
            Schema::table('bot_settings', function (Blueprint $table) {
                $table->renameColumn('voyage_api_key', 'jina_api_key');
            });
        }
    }
};
