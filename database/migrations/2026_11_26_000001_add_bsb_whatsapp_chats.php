<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp chats from BSB's WhatsApp Messaging API webhook.
 *
 * BSB posts every incoming WhatsApp message and every status change of an
 * outgoing one to a URL of ours. The payload doesn't say which BSB account it
 * belongs to, so each account gets its own URL (webhook_key), and BSB signs
 * each delivery with a secret (webhook_secret) we check.
 *
 * The chats are stored in social_conversations / social_messages like Messenger
 * and Instagram ones, so the inbox and response times cover them too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('webhook_key', 64)->nullable()->unique()->after('access_token');
            $table->text('webhook_secret')->nullable()->after('webhook_key'); // encrypted
            // Last delivery from BSB, for the setup screen. Own columns, not meta: webhooks
            // arrive all the time and must not race the balance check that rewrites meta.
            $table->timestamp('webhook_last_at')->nullable()->after('webhook_secret');
            $table->string('webhook_last_event', 40)->nullable()->after('webhook_last_at');
        });

        Schema::table('social_messages', function (Blueprint $table) {
            $table->string('type', 20)->nullable()->after('message');   // text | image | video | audio | document...
            $table->string('status', 12)->nullable()->after('type');    // outgoing: sent | delivered | read | failed
            $table->string('error', 500)->nullable()->after('status');  // why an outgoing message failed
            $table->json('media')->nullable()->after('error');          // mime_type, filename, file_size, voice, download_url, expires_at, path
        });
    }

    public function down(): void
    {
        Schema::table('social_messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'status', 'error', 'media']);
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropUnique(['webhook_key']);
            $table->dropColumn(['webhook_key', 'webhook_secret', 'webhook_last_at', 'webhook_last_event']);
        });
    }
};
