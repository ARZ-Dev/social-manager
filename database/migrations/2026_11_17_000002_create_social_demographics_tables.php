<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Latest audience snapshot of a Page / Instagram account, e.g.
        // follower_demographics · age · 25-34 = 1200. Replaced on every fetch.
        Schema::create('social_demographics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 100);   // follower_demographics, page_follows_country...
            $table->string('dimension', 20); // age | gender | country | city
            $table->string('segment', 150);  // 25-34, F, LB, "Beirut, Lebanon"...
            $table->bigInteger('value')->default(0);
            $table->timestamps();

            $table->unique(['social_account_id', 'metric', 'dimension', 'segment'], 'social_demographics_unique');
        });

        // Ad results over the fetch window split by a breakdown (age & gender,
        // country, placement, device). One row per ad and segment.
        Schema::create('social_ad_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete(); // the ad account
            $table->string('ad_external_id', 64);
            $table->string('breakdown', 30); // key of config('meta.ad_breakdowns')
            $table->string('segment', 150);  // breakdown values joined with "|", e.g. "25-34|female"
            $table->decimal('spend', 14, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();

            $table->unique(['social_account_id', 'ad_external_id', 'breakdown', 'segment'], 'social_ad_breakdowns_unique');
            $table->index(['social_account_id', 'breakdown']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_ad_breakdowns');
        Schema::dropIfExists('social_demographics');
    }
};
