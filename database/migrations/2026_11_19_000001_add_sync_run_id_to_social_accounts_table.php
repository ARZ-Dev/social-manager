<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            // Which background fetch owns the account right now. Stopping a fetch
            // clears it; a newer fetch replaces it. The running job checks it.
            $table->string('sync_run_id', 36)->nullable()->after('sync_queued_at');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn('sync_run_id');
        });
    }
};
