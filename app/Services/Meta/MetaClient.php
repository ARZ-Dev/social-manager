<?php

namespace App\Services\Meta;

use App\Models\Company;
use GuzzleHttp\Client;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Meta Graph API (read-only GET calls).
 * Each company uses its own Meta app, so build it with forCompany().
 */
class MetaClient
{
    protected string $base;

    /**
     * One HTTP client for every call, so the connection to graph.facebook.com
     * stays open (keep-alive) instead of a new TCP + TLS connection per request.
     * A fetch makes hundreds of calls; opening that many connections can trip
     * server firewalls/connection limits (seen as "Operation not permitted").
     */
    protected ?Client $client = null;

    public function __construct(protected string $appId, protected string $appSecret)
    {
        $this->base = 'https://graph.facebook.com/' . config('meta.graph_version');
    }

    public static function forCompany(Company $company): self
    {
        if (!$company->hasMetaApp()) {
            throw new MetaApiException("{$company->name} has no Meta App ID / App Secret. Add them in the company settings.");
        }

        return new self($company->meta_app_id, $company->meta_app_secret);
    }

    public function loginUrl(string $redirectUri, string $state): string
    {
        return 'https://www.facebook.com/' . config('meta.graph_version') . '/dialog/oauth?' . http_build_query([
            'client_id' => $this->appId,
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
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        $long = $this->request('oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
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
            'appsecret_proof' => hash_hmac('sha256', $token, $this->appSecret),
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
            $response = $this->call(fn () => $this->http()->get($next));
        }
    }

    protected function request(string $path, array $params): array
    {
        return $this->call(fn () => $this->http()->get($this->base . '/' . ltrim($path, '/'), $params));
    }

    /** Run a request; a dropped connection becomes a normal Meta error (a warning, not a crash). */
    protected function call(callable $request): array
    {
        try {
            return $this->send($request());
        } catch (ConnectionException $e) {
            throw new MetaApiException('Could not reach Meta: ' . $e->getMessage());
        }
    }

    /** A request on the shared client; up to 3 attempts if the connection itself drops. */
    protected function http(): PendingRequest
    {
        // Built through the Http factory so Http::fake() still applies in tests.
        $this->client ??= Http::buildClient();

        return Http::setClient($this->client)
            ->connectTimeout(15)
            ->timeout(60)
            ->retry(3, 1000, fn ($e) => $e instanceof ConnectionException, throw: false);
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
