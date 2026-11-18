<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            // Where the account is in its latest fetch run (sync_run_id):
            // queued | running | done | failed | stopped.
            $table->string('sync_state', 10)->nullable()->after('sync_run_id');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn('sync_state');
        });
    }
};
