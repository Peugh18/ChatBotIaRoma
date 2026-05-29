<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products') || !Schema::hasTable('categories')) {
            return;
        }

        if ($this->foreignKeyExists()) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('products') || !$this->foreignKeyExists()) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });
    }

    protected function foreignKeyExists(): bool
    {
        $fk = Schema::getConnection()->selectOne(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
             AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'products_category_id_foreign'"
        );

        return $fk !== null;
    }
};
