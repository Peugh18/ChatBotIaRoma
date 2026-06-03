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
        if (!Schema::hasColumn('product_variants', 'embedding')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->json('embedding')->nullable()->comment('CLIP embedding vector as array of floats');
                $table->timestamp('embedding_indexed_at')->nullable()->comment('When embedding was last indexed');
                $table->string('embedding_model')->nullable()->comment('Model used for embedding (e.g. openai/clip-vit-large-patch14)');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedding_indexed_at', 'embedding_model']);
        });
    }
};
