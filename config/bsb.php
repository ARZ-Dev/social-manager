<?php

/*
|--------------------------------------------------------------------------
| BSB (bestsmsbulk.com) SMS / WhatsApp integration
|--------------------------------------------------------------------------
|
| BSB's API only lists the messages sent *today*, with their ID, recipient and
| delivery status. So the app checks every few minutes (see routes/console.php)
| and keeps its own log. The scheduler must run for that:
| `php artisan schedule:run` every minute (cron or Windows Task Scheduler).
|
*/

return [
    'base_url' => env('BSB_BASE_URL', 'https://www.bestsmsbulk.com/bestsmsbulkapi'),

    // How many of today's messages one check asks for, per route (sms / wp).
    'status_limit' => (int) env('BSB_STATUS_LIMIT', 10000),

    // Messages not yet delivered or failed are re-checked for this many days.
    'recheck_days' => (int) env('BSB_RECHECK_DAYS', 7),

    // Automatic check interval in minutes (also used to warn when checks stop).
    'poll_minutes' => (int) env('BSB_POLL_MINUTES', 10),

    // WhatsApp chats (webhook): files of incoming messages bigger than this aren't kept.
    // BSB's own link to them still works for 7 days.
    'media_max_mb' => (int) env('BSB_MEDIA_MAX_MB', 16),
];
