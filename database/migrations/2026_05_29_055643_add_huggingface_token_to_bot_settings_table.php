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
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->string('huggingface_token')->nullable()->comment('Hugging Face API token for CLIP embeddings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->dropColumn('huggingface_token');
        });
    }
};
