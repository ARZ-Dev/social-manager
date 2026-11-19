<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Ads and BSB (bestsmsbulk.com) next to the Meta accounts.
 *
 * - Each company connects Google Ads through its own Google Cloud OAuth client
 *   and Google Ads developer token, like it does with its own Meta app.
 * - Google Ads campaigns are stored in social_ads (with conversions and
 *   engagement), their audience splits in social_ad_breakdowns, and the
 *   account's day-by-day results in social_ad_daily_stats.
 * - BSB only reports message IDs, recipients and delivery status, and only for
 *   today's messages, so every check adds to social_bulk_messages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('google_ads_developer_token')->nullable()->after('meta_app_secret'); // encrypted
            $table->string('google_ads_client_id')->nullable()->after('google_ads_developer_token');
            $table->text('google_ads_client_secret')->nullable()->after('google_ads_client_id'); // encrypted
        });

        Schema::table('social_ads', function (Blueprint $table) {
            $table->decimal('conversions', 14, 2)->default(0)->after('clicks');
            $table->decimal('conversion_value', 14, 2)->default(0)->after('conversions');
            $table->unsignedBigInteger('interactions')->default(0)->after('conversion_value');
            $table->unsignedBigInteger('engagements')->default(0)->after('interactions');
            $table->unsignedBigInteger('video_views')->default(0)->after('engagements');
        });

        Schema::table('social_ad_breakdowns', function (Blueprint $table) {
            $table->decimal('conversions', 14, 2)->default(0)->after('clicks');
        });

        // One row per ad account and day: the account's whole results that day.
        Schema::create('social_ad_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('spend', 14, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('conversions', 14, 2)->default(0);
            $table->decimal('conversion_value', 14, 2)->default(0);
            $table->unsignedBigInteger('interactions')->default(0);
            $table->unsignedBigInteger('engagements')->default(0);
            $table->unsignedBigInteger('video_views')->default(0);
            $table->timestamps();

            $table->unique(['social_account_id', 'date']);
        });

        // Messages sent through a BSB account (SMS or WhatsApp), as BSB reports them.
        Schema::create('social_bulk_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('route', 4);         // sms | wp
            $table->string('ack', 32);          // BSB's message ID
            $table->string('recipient', 32)->nullable();
            $table->string('country', 2)->nullable(); // from the number's calling code
            $table->string('status', 12)->nullable(); // DELIVERED | SENT | PENDING | FAILED
            // BSB gives no send time: this is when a check first saw the message,
            // so it is at most one check interval after it was sent.
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('delivered_at')->nullable(); // first check that saw it delivered
            $table->timestamps();

            $table->unique(['social_account_id', 'route', 'ack']);
            $table->index(['social_account_id', 'first_seen_at']);
            $table->index(['social_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_bulk_messages');
        Schema::dropIfExists('social_ad_daily_stats');

        Schema::table('social_ad_breakdowns', function (Blueprint $table) {
            $table->dropColumn('conversions');
        });

        Schema::table('social_ads', function (Blueprint $table) {
            $table->dropColumn(['conversions', 'conversion_value', 'interactions', 'engagements', 'video_views']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['google_ads_developer_token', 'google_ads_client_id', 'google_ads_client_secret']);
        });
    }
};
