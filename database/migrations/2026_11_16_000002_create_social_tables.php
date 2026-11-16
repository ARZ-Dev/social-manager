<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A Facebook Page, Instagram business account or ad account linked to a company.
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('social_accounts')->cascadeOnDelete();
            $table->string('platform', 20); // facebook | instagram | ads
            $table->string('external_id');
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->text('picture_url')->nullable();
            $table->text('access_token')->nullable(); // encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->json('meta')->nullable(); // followers, currency, etc.
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'platform', 'external_id']);
        });

        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('media_type', 50)->nullable();
            $table->text('message')->nullable();
            $table->text('media_url')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->text('permalink')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('like_count')->default(0);
            $table->unsignedBigInteger('comment_count')->default(0);
            $table->unsignedBigInteger('share_count')->default(0);
            $table->json('insights')->nullable(); // metric => value
            $table->timestamps();

            $table->unique(['social_account_id', 'external_id']);
            $table->index('posted_at');
        });

        Schema::create('social_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('parent_external_id')->nullable();
            $table->string('author_name')->nullable();
            $table->text('message')->nullable();
            $table->unsignedBigInteger('like_count')->default(0);
            $table->timestamp('commented_at')->nullable();
            $table->timestamps();

            $table->unique(['social_post_id', 'external_id']);
        });

        // Daily account-level metrics (reach, views, engagement, follows...).
        Schema::create('social_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 100);
            $table->date('date');
            $table->bigInteger('value')->default(0);
            $table->timestamps();

            $table->unique(['social_account_id', 'metric', 'date']);
        });

        Schema::create('social_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('participant_name')->nullable();
            $table->text('snippet')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'external_id']);
        });

        Schema::create('social_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('from_name')->nullable();
            $table->string('from_id')->nullable();
            $table->boolean('from_page')->default(false);
            $table->text('message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['social_conversation_id', 'external_id']);
        });

        // Boosted posts / ads from the company's ad account(s).
        Schema::create('social_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name')->nullable();
            $table->string('status', 50)->nullable();
            $table->string('effective_status', 50)->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('objective', 100)->nullable();
            $table->string('story_id')->nullable(); // linked FB post / IG media
            $table->decimal('spend', 14, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_ads');
        Schema::dropIfExists('social_messages');
        Schema::dropIfExists('social_conversations');
        Schema::dropIfExists('social_insights');
        Schema::dropIfExists('social_comments');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_accounts');
    }
};
