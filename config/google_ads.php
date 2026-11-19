<?php

/*
|--------------------------------------------------------------------------
| Google Ads integration
|--------------------------------------------------------------------------
|
| Each company enters its own developer token and Google Cloud OAuth client
| in Companies > Edit. Google retires an API version about a year after its
| release: when it does, raise GOOGLE_ADS_API_VERSION.
|
*/

return [
    'api_version' => env('GOOGLE_ADS_API_VERSION', 'v26'),

    // Read-only access to Google Ads reports. (Google has no narrower scope.)
    'scopes' => ['https://www.googleapis.com/auth/adwords'],

    // Audience splits shown in the Ads tab (name => dimension, see SocialAdBreakdown::label()).
    'breakdowns' => [
        'age' => 'age',
        'gender' => 'gender',
        'country' => 'country',
        'device' => 'device_platform',
        'network' => 'network',
    ],

    // Client accounts listed under a manager account when connecting: how many levels down.
    'max_client_level' => (int) env('GOOGLE_ADS_MAX_CLIENT_LEVEL', 2),
];
