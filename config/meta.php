<?php

/*
|--------------------------------------------------------------------------
| Meta (Facebook / Instagram / Ads) integration
|--------------------------------------------------------------------------
|
| Metric names change between Graph API versions. If Meta deprecates one,
| the fetch reports it as a warning and keeps going; adjust the lists here.
|
*/

return [
    'graph_version' => env('META_GRAPH_VERSION', 'v23.0'),

    // How far back the Fetch button reads.
    'history_days' => (int) env('META_HISTORY_DAYS', 90),

    // Safety cap on pages of results per list call (each page is up to 100 items).
    'max_pages' => (int) env('META_MAX_PAGES', 20),

    // Read-only permissions requested when connecting.
    'scopes' => [
        'business_management',
        'pages_show_list',
        'pages_read_engagement',
        'pages_read_user_content',
        'read_insights',
        'pages_messaging',
        'instagram_basic',
        'instagram_manage_insights',
        'instagram_manage_comments',
        'instagram_manage_messages',
        'ads_read',
    ],

    'metrics' => [
        'facebook_page' => ['page_impressions_unique', 'page_post_engagements', 'page_views_total', 'page_follows'],
        'facebook_post' => ['post_impressions_unique', 'post_clicks', 'post_reactions_by_type_total'],
        'instagram_account' => ['reach', 'follower_count'],
        'instagram_media' => ['reach', 'views', 'likes', 'comments', 'saved', 'shares', 'total_interactions'],
    ],

    // How daily account metrics appear on the Overview tab, in chart order.
    // agg: 'sum' adds the daily values, 'last' shows the latest one (running totals).
    // kpi: 'reach' counts toward the Reach card, 'new_followers' / 'followers_total'
    // toward the follower growth line. Metrics not listed still chart under their raw name.
    'daily_metrics' => [
        'reach' => ['label' => 'Reach', 'agg' => 'sum', 'kpi' => 'reach'],
        // Meta calls this "Viewers"; it replaced Page reach in 2025, so it charts with Instagram reach.
        'page_total_media_view_unique' => ['label' => 'Reach', 'agg' => 'sum', 'kpi' => 'reach'],
        'page_impressions_unique' => ['label' => 'Reach', 'agg' => 'sum', 'kpi' => 'reach'],
        'page_post_engagements' => ['label' => 'Post engagements', 'agg' => 'sum'],
        'page_views_total' => ['label' => 'Page views', 'agg' => 'sum'],
        'follower_count' => ['label' => 'New followers', 'agg' => 'sum', 'kpi' => 'new_followers'],
        'page_follows' => ['label' => 'Followers', 'agg' => 'last', 'kpi' => 'followers_total'],
    ],

    // Readable names for per-post metrics (likes / comments are shown separately).
    'post_metric_labels' => [
        'post_impressions_unique' => 'Reach',
        'post_total_media_view_unique' => 'Reach', // Meta's "Viewers"
        'post_clicks' => 'Clicks',
        'post_reactions_by_type_total' => 'Reactions',
        'reach' => 'Reach',
        'views' => 'Views',
        'saved' => 'Saves',
        'shares' => 'Shares',
        'total_interactions' => 'Interactions',
    ],

    // Audience demographics. Each fetch replaces the previous snapshot.
    'demographics' => [
        // Facebook Pages: metric => dimension. Meta removed age and gender for
        // Pages in 2024, so only follower location is left.
        'facebook_page' => [
            'page_follows_country' => 'country',
            'page_follows_city' => 'city',
        ],
        // Instagram: each metric is requested once per breakdown. Meta returns
        // nothing below 100 followers (or 100 engagements for the engaged audience).
        'instagram_account' => [
            'metrics' => ['follower_demographics', 'engaged_audience_demographics'],
            'breakdowns' => ['age', 'gender', 'country', 'city'],
            'timeframe' => 'this_month', // this_week | this_month
        ],
    ],

    // Ad results over the history window, split by these Ads Insights
    // breakdowns (name => comma-separated breakdowns Meta allows together).
    'ad_breakdowns' => [
        'age_gender' => 'age,gender',
        'country' => 'country',
        'placement' => 'publisher_platform,platform_position',
        'device' => 'device_platform',
    ],
];
