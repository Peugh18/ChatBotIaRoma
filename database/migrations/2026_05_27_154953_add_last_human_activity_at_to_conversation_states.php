<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_states', function (Blueprint $table) {
            $table->timestamp('last_human_activity_at')->nullable()->after('last_activity_at');
            $table->boolean('is_auto_escalated')->default(false)->after('requires_human');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversation_states', function (Blueprint $table) {
            $table->dropColumn(['last_human_activity_at', 'is_auto_escalated']);
        });
    }
};
