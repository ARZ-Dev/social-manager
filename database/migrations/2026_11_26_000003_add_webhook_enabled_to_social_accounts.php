<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An on/off switch for a BSB account's WhatsApp chats (its webhook). Off: BSB's
 * deliveries are ignored and the chats are hidden, but kept for when it's back on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->boolean('webhook_enabled')->default(true)->after('webhook_last_event');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn('webhook_enabled');
        });
    }
};
