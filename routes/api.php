<?php

use App\Http\Controllers\BsbWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Machine-to-machine endpoints (prefix /api). No session, cookies or CSRF here:
| callers are other services, checked by each endpoint itself.
*/

// BSB WhatsApp Messaging API: incoming messages and status changes, one URL per BSB account.
Route::post('/webhooks/bsb/{key}', BsbWebhookController::class)
    ->where('key', '[A-Za-z0-9]{20,64}')
    ->name('webhooks.bsb');
