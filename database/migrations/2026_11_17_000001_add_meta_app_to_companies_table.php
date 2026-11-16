<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Each company connects through its own Meta (Facebook) app.
            $table->string('meta_app_id')->nullable()->after('address');
            $table->text('meta_app_secret')->nullable()->after('meta_app_id'); // encrypted
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['meta_app_id', 'meta_app_secret']);
        });
    }
};
