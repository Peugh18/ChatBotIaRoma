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
        Schema::create('conversation_states', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->unique();
            $table->string('current_state')->default('greeting'); // greeting, browsing, selecting, confirming, payment, shipping, done
            $table->json('context')->nullable(); // {product_id, color, size, address, payment_method, etc.}
            $table->timestamp('last_activity_at')->useCurrent();
            $table->boolean('requires_human')->default(false);
            $table->string('last_reminder_sent')->default('none'); // none, 3min, 15min
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_states');
    }
};
