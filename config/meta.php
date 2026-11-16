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
];
