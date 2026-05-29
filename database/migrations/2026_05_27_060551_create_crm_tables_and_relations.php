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
        // 1. Crear tabla de clientes (customers)
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('segment')->nullable(); // lead, interested, considering, repeat_customer, etc.
            $table->decimal('lifetime_value', 10, 2)->default(0.00);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('phone_number');
        });

        // 2. Crear tabla de órdenes/ventas (orders)
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('conversation_state_id')->nullable()->constrained('conversation_states')->onDelete('set null');
            $table->enum('status', ['pending', 'paid', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->enum('shipping_method', ['shalom', 'motorizado', 'none'])->default('none');
            $table->decimal('shipping_cost', 10, 2)->default(0.00);
            $table->enum('payment_method', ['yape', 'card', 'link', 'cash'])->default('yape');
            $table->string('payment_proof_url')->nullable();
            $table->string('district')->nullable();
            $table->string('full_address')->nullable();
            $table->json('location')->nullable();
            $table->decimal('amount_subtotal', 10, 2)->default(0.00);
            $table->decimal('amount_total', 10, 2)->default(0.00);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('status');
        });

        // 3. Crear tabla de ítems de órdenes (order_items)
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('set null');
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2)->default(0.00);
            $table->timestamps();
        });

        // 4. Crear tabla de transferencia a humanos (agent_handoffs)
        Schema::create('agent_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_state_id')->constrained('conversation_states')->onDelete('cascade');
            $table->text('reason')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->foreignId('taken_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('taken_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
        });

        // 5. Agregar relaciones a tablas existentes
        Schema::table('conversation_states', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('phone_number')->constrained()->nullOnDelete();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('phone_number')->constrained()->nullOnDelete();
            $table->foreignId('conversation_state_id')->nullable()->after('customer_id')->constrained('conversation_states')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // En SQLite, deshabilitamos FK antes de dropear columnas/tablas
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_state_id');
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::table('conversation_states', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::dropIfExists('agent_handoffs');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
    }
};
