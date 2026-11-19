<?php

namespace App\Services\GoogleAds;

use App\Models\Company;
use GuzzleHttp\Client;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around Google OAuth and the Google Ads REST API (read-only reports).
 * Each company uses its own OAuth client and developer token: build it with forCompany().
 */
class GoogleAdsClient
{
    public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    protected string $base;

    /** Shared HTTP client, so the connection to Google stays open between calls. */
    protected ?Client $client = null;

    /** @var array<string, array{token: string, expires: int}> Access tokens by refresh-token hash. */
    protected array $accessTokens = [];

    public function __construct(protected string $developerToken, protected string $clientId, protected string $clientSecret)
    {
        $this->base = 'https://googleads.googleapis.com/' . config('google_ads.api_version');
    }

    public static function forCompany(Company $company): self
    {
        if (!$company->hasGoogleAdsApp()) {
            throw new GoogleAdsException("{$company->name} has no Google Ads developer token / OAuth client. Add them in the company settings.");
        }

        return new self($company->google_ads_developer_token, $company->google_ads_client_id, $company->google_ads_client_secret);
    }

    /** "123-456-7890" or "customers/1234567890" → "1234567890". */
    public static function customerId(string $id): string
    {
        return preg_replace('/\D/', '', $id);
    }

    /** "1234567890" → "123-456-7890", as Google Ads shows it. */
    public static function formatCustomerId(string $id): string
    {
        $id = self::customerId($id);

        return strlen($id) === 10 ? substr($id, 0, 3) . '-' . substr($id, 3, 3) . '-' . substr($id, 6) : $id;
    }

    // ------------------------------------------------------------------ OAuth

    /** Google sign-in page. prompt=consent so Google always returns a refresh token. */
    public function loginUrl(string $redirectUri, string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', config('google_ads.scopes')),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Exchange the OAuth code for tokens.
     *
     * @return array{refresh_token: ?string, access_token: string}
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        $json = $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        if (!empty($json['refresh_token'])) {
            $this->remember($json['refresh_token'], $json);
        }

        return ['refresh_token' => $json['refresh_token'] ?? null, 'access_token' => $json['access_token']];
    }

    /** A valid access token for a refresh token (they last an hour and are reused until then). */
    public function accessToken(string $refreshToken): string
    {
        $key = hash('sha256', $refreshToken);
        if (($this->accessTokens[$key]['expires'] ?? 0) > time() + 60) {
            return $this->accessTokens[$key]['token'];
        }

        $json = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
        $this->remember($refreshToken, $json);

        return $json['access_token'];
    }

    protected function remember(string $refreshToken, array $json): void
    {
        $this->accessTokens[hash('sha256', $refreshToken)] = [
            'token' => $json['access_token'],
            'expires' => time() + (int) ($json['expires_in'] ?? 3600),
        ];
    }

    protected function token(array $params): array
    {
        $response = $this->call(fn () => $this->http()->asForm()->post(self::TOKEN_URL, $params + [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]));
        $json = $response->json() ?? [];

        if ($response->failed() || empty($json['access_token'])) {
            $reason = is_string($json['error'] ?? null) ? $json['error'] : null;
            $message = $json['error_description'] ?? $reason ?? ('HTTP ' . $response->status());
            if ($reason === 'invalid_grant') {
                $message = "Google access expired or was revoked ($message). Connect Google Ads again.";
            }
            throw GoogleAdsException::withReason($message, $reason, $response->status());
        }

        return $json;
    }

    // -------------------------------------------------------------- Reports

    /**
     * IDs of the Google Ads accounts the signed-in user can reach directly.
     *
     * @return string[]
     */
    public function listAccessibleCustomers(string $refreshToken): array
    {
        $json = $this->send($this->call(fn () => $this->ads($refreshToken)->get($this->base . '/customers:listAccessibleCustomers')));

        return array_map(fn ($name) => self::customerId($name), $json['resourceNames'] ?? []);
    }

    /**
     * Run a GAQL query and yield each result row (camelCase keys, as Google sends them).
     * Google leaves out zero / false values, so read every field with a default.
     * $loginCustomerId is the manager account the access goes through, if any.
     */
    public function search(string $customerId, string $query, string $refreshToken, ?string $loginCustomerId = null): \Generator
    {
        $url = $this->base . '/customers/' . self::customerId($customerId) . '/googleAds:search';
        $body = ['query' => $query];

        do {
            $json = $this->send($this->call(fn () => $this->ads($refreshToken, $loginCustomerId)->post($url, $body)));
            foreach ($json['results'] ?? [] as $row) {
                yield $row;
            }
            $body['pageToken'] = $json['nextPageToken'] ?? null;
        } while ($body['pageToken']);
    }

    /** @return array<int, array> */
    public function searchAll(string $customerId, string $query, string $refreshToken, ?string $loginCustomerId = null): array
    {
        return iterator_to_array($this->search($customerId, $query, $refreshToken, $loginCustomerId), false);
    }

    /**
     * Every Google Ads account the signed-in user can read: the ones they reach
     * directly, plus the client accounts under any manager account they reach.
     * Accounts Google refuses are returned in 'skipped' with the reason.
     *
     * @return array{accounts: array<int, array{id: string, name: string, currency: ?string, time_zone: ?string, login_customer_id: ?string, manager_name: ?string, active: bool, test: bool}>, skipped: string[]}
     */
    public function discoverAccounts(string $refreshToken): array
    {
        $accounts = [];
        $skipped = [];
        $maxLevel = max(1, (int) config('google_ads.max_client_level'));

        foreach ($this->listAccessibleCustomers($refreshToken) as $id) {
            try {
                $c = $this->searchAll($id, 'SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.time_zone, '
                    . 'customer.manager, customer.status, customer.test_account FROM customer LIMIT 1', $refreshToken, $id)[0]['customer'] ?? [];
            } catch (GoogleAdsException $e) {
                $skipped[] = self::formatCustomerId($id) . ': ' . $e->getMessage();
                continue;
            }

            if (empty($c['manager'])) {
                // Direct access needs no manager in between, so it wins over a listing below.
                $accounts[$id] = $this->accountRow($id, $c, null, null);
                continue;
            }

            $managerName = ($c['descriptiveName'] ?? '') ?: self::formatCustomerId($id);
            try {
                $clients = $this->search($id, 'SELECT customer_client.id, customer_client.descriptive_name, customer_client.currency_code, '
                    . 'customer_client.time_zone, customer_client.status, customer_client.test_account, customer_client.level '
                    . "FROM customer_client WHERE customer_client.manager = FALSE AND customer_client.level <= $maxLevel", $refreshToken, $id);
                foreach ($clients as $row) {
                    $cc = $row['customerClient'] ?? [];
                    $clientId = (string) ($cc['id'] ?? '');
                    if ($clientId !== '' && !isset($accounts[$clientId])) {
                        $accounts[$clientId] = $this->accountRow($clientId, $cc, $id, $managerName);
                    }
                }
            } catch (GoogleAdsException $e) {
                $skipped[] = $managerName . ' (manager): ' . $e->getMessage();
            }
        }

        return ['accounts' => array_values($accounts), 'skipped' => $skipped];
    }

    protected function accountRow(string $id, array $c, ?string $loginCustomerId, ?string $managerName): array
    {
        return [
            'id' => self::customerId($id),
            'name' => ($c['descriptiveName'] ?? '') ?: self::formatCustomerId($id),
            'currency' => $c['currencyCode'] ?? null,
            'time_zone' => $c['timeZone'] ?? null,
            'login_customer_id' => $loginCustomerId ? self::customerId($loginCustomerId) : null,
            'manager_name' => $managerName,
            'active' => ($c['status'] ?? 'ENABLED') === 'ENABLED',
            'test' => !empty($c['testAccount']),
        ];
    }

    // ------------------------------------------------------------- Plumbing

    protected function ads(string $refreshToken, ?string $loginCustomerId = null): PendingRequest
    {
        $headers = ['developer-token' => $this->developerToken];
        if ($loginCustomerId) {
            $headers['login-customer-id'] = self::customerId($loginCustomerId);
        }

        return $this->http()->withToken($this->accessToken($refreshToken))->withHeaders($headers)->acceptJson();
    }

    /** A request on the shared client; up to 3 attempts if the connection itself drops. */
    protected function http(): PendingRequest
    {
        // Built through the Http factory so Http::fake() still applies in tests.
        $this->client ??= Http::buildClient();

        return Http::setClient($this->client)
            ->connectTimeout(15)
            ->timeout(120)
            ->retry(3, 1000, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    /** Run a request; a dropped connection becomes a normal Google error (a warning, not a crash). */
    protected function call(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            throw new GoogleAdsException('Could not reach Google: ' . $e->getMessage());
        }
    }

    /**
     * Decode a Google Ads API answer. Errors carry Google Ads' own messages in
     * error.details[].errors[]; those say more than the top-level message.
     */
    protected function send(Response $response): array
    {
        $json = $response->json() ?? [];
        if (!$response->failed() && !isset($json['error'])) {
            return $json;
        }

        $error = is_array($json['error'] ?? null) ? $json['error'] : [];
        $messages = [];
        $reason = null;
        foreach ($error['details'] ?? [] as $detail) {
            foreach ($detail['errors'] ?? [] as $e) {
                $messages[] = $e['message'] ?? '';
                if ($reason === null && is_array($e['errorCode'] ?? null)) {
                    $reason = (string) (array_values($e['errorCode'])[0] ?? '') ?: null;
                }
            }
        }
        $message = implode(' ', array_unique(array_filter($messages))) ?: ($error['message'] ?? ('HTTP ' . $response->status()));

        throw GoogleAdsException::withReason($message, $reason ?? ($error['status'] ?? null), (int) ($error['code'] ?? $response->status()));
    }
}
