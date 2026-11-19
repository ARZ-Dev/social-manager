<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            // While the account is being fetched: how far it got (0-100) and
            // which step it's on ("Posts and comments", "Audience"...).
            $table->unsignedTinyInteger('sync_progress')->nullable()->after('sync_state');
            $table->string('sync_step', 30)->nullable()->after('sync_progress');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn(['sync_progress', 'sync_step']);
        });
    }
};
