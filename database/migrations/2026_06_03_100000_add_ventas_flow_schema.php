<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status', 20)->default('disponible')->after('category_id');
            $table->index('status');
        });

        Schema::create('producto_similares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('similar_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'similar_product_id']);
        });

        Schema::create('sedes_shalom', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('ciudad')->nullable();
            $table->string('region')->default('lima');
            $table->decimal('costo', 10, 2)->default(10);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('bot_settings', function (Blueprint $table) {
            $table->text('mensaje_presentacion')->nullable()->after('welcome_message');
        });
    }

    public function down(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->dropColumn('mensaje_presentacion');
        });

        Schema::dropIfExists('sedes_shalom');
        Schema::dropIfExists('producto_similares');

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
