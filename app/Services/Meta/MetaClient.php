<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Meta Graph API (read-only GET calls).
 */
class MetaClient
{
    protected string $base;

    public function __construct()
    {
        $this->base = 'https://graph.facebook.com/' . config('meta.graph_version');
    }

    public static function isConfigured(): bool
    {
        return filled(config('meta.app_id')) && filled(config('meta.app_secret'));
    }

    public function loginUrl(string $redirectUri, string $state): string
    {
        return 'https://www.facebook.com/' . config('meta.graph_version') . '/dialog/oauth?' . http_build_query([
            'client_id' => config('meta.app_id'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', config('meta.scopes')),
        ]);
    }

    /**
     * Exchange the OAuth code for a long-lived (~60 day) user token.
     *
     * @return array{access_token: string, expires_in: ?int}
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        $short = $this->request('oauth/access_token', [
            'client_id' => config('meta.app_id'),
            'client_secret' => config('meta.app_secret'),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        $long = $this->request('oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('meta.app_id'),
            'client_secret' => config('meta.app_secret'),
            'fb_exchange_token' => $short['access_token'],
        ]);

        return [
            'access_token' => $long['access_token'],
            'expires_in' => $long['expires_in'] ?? null,
        ];
    }

    public function get(string $path, array $params, string $token): array
    {
        return $this->request($path, $params + [
            'access_token' => $token,
            'appsecret_proof' => hash_hmac('sha256', $token, config('meta.app_secret')),
        ]);
    }

    /**
     * Yield every item of a paginated list, following `paging.next`.
     */
    public function paginate(string $path, array $params, string $token, ?int $maxPages = null): \Generator
    {
        $maxPages ??= config('meta.max_pages');
        $response = $this->get($path, $params, $token);

        for ($page = 1; ; $page++) {
            foreach ($response['data'] ?? [] as $item) {
                yield $item;
            }

            $next = $response['paging']['next'] ?? null;
            if (!$next || $page >= $maxPages) {
                return;
            }
            $response = $this->send(Http::timeout(60)->get($next));
        }
    }

    protected function request(string $path, array $params): array
    {
        return $this->send(Http::timeout(60)->get($this->base . '/' . ltrim($path, '/'), $params));
    }

    protected function send($response): array
    {
        $json = $response->json() ?? [];

        if ($response->failed() || isset($json['error'])) {
            $message = $json['error']['message'] ?? ('HTTP ' . $response->status());
            throw new MetaApiException($message, (int) ($json['error']['code'] ?? $response->status()));
        }

        return $json;
    }
}
