<?php

namespace App\Services\GoogleAds;

/**
 * An error from Google (OAuth or the Google Ads API). $reason is Google's
 * error code when it sends one, e.g. DEVELOPER_TOKEN_NOT_APPROVED or invalid_grant.
 */
class GoogleAdsException extends \RuntimeException
{
    public ?string $reason = null;

    public static function withReason(string $message, ?string $reason, int $code = 0): self
    {
        $e = new self($message, $code);
        $e->reason = $reason;

        return $e;
    }
}
