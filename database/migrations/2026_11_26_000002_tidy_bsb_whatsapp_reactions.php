<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * WhatsApp reactions received before reactions were understood were kept as
 * BSB's text ("👍 *Reaction Received*: 👍 🔗 *Reacted to Message ID*: wamid…")
 * with an empty file. Keep only the emoji, like new ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('social_messages')->where('type', 'reaction')->orderBy('id')->each(function ($m) {
            $text = trim((string) $m->message);
            if (!str_contains($text, 'Reaction Received')) {
                return; // already tidy
            }
            $emoji = preg_match('/Reaction Received\**:[ \t]*([^\s*]+)/u', $text, $match) ? $match[1] : null;

            DB::table('social_messages')->where('id', $m->id)->update(['message' => $emoji, 'media' => null]);
            DB::table('social_conversations')
                ->where('id', $m->social_conversation_id)
                ->where('snippet', 'like', '%Reaction Received%')
                ->update(['snippet' => $emoji ? 'Reacted ' . $emoji : 'Removed a reaction']);
        });
    }

    public function down(): void
    {
        // Nothing to undo: the original text only repeated the emoji and a message ID.
    }
};
