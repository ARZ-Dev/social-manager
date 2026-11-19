<?php

namespace App\Services\Bsb;

use App\Models\Social\SocialAccount;
use GuzzleHttp\Client;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Read-only calls to BSB's API (https://www.bestsmsbulk.com/pro-support/api_documentation_bsb):
 * balance and delivery status. BSB's API can also send messages; this app doesn't.
 *
 * Its answers aren't uniform (JSON objects, JSON lists or plain text, errors
 * as "status": 300 or as a bare sentence), so each call reads them defensively.
 */
class BsbClient
{
    protected string $base;

    protected ?Client $client = null;

    public function __construct(protected string $apiKey, protected string $apiSecret)
    {
        $this->base = rtrim(config('bsb.base_url'), '/');
    }

    public static function forAccount(SocialAccount $account): self
    {
        $creds = $account->bsbCredentials();
        if (!$creds) {
            throw new BsbException('The BSB API key is missing. Disconnect the account and connect it again.');
        }

        return new self($creds['api_key'], $creds['api_secret']);
    }

    /**
     * SMS credit and WhatsApp balance. Also the way to check the key and secret.
     *
     * @return array{sms: float, wp: float}
     */
    public function balance(): array
    {
        $response = $this->post('/getBalanceJson.php', [$this->credentials()]);
        $json = $this->first($response->json());

        if ((int) ($json['status'] ?? 0) !== 201) {
            throw new BsbException($this->errorText($response));
        }

        return ['sms' => (float) ($json['smsbalance'] ?? 0), 'wp' => (float) ($json['wpbalance'] ?? 0)];
    }

    /**
     * Today's messages on one route (BSB keeps no history in its API).
     *
     * @param 'sms'|'wp' $route
     * @return array<int, array{ack: string, status: ?string, recipient: ?string}>
     */
    public function todaysMessages(string $route, int $limit): array
    {
        $response = $this->post('/getMessageStatusJson.php', [$this->credentials() + ['route' => $route, 'limit' => $limit]]);
        $rows = $this->statusRows($response->json());

        if ($rows !== null) {
            return $rows;
        }

        $error = $this->errorText($response);
        if (stripos($error, 'no msgs') !== false || stripos($error, 'no messages') !== false) {
            return []; // nothing sent today on this route
        }

        throw new BsbException($error);
    }

    /**
     * Current status of the given message IDs (at most 500 per call).
     *
     * @param 'sms'|'wp' $route
     * @param int[] $acks
     * @return array<string, array{ack: string, status: ?string, recipient: ?string}> by ack, found ones only
     */
    public function statusesByAcks(string $route, array $acks): array
    {
        $response = $this->post('/getMessageStatusByAcksJson.php', $this->credentials() + [
            'route' => $route,
            'acks' => array_values(array_map('intval', $acks)),
        ]);
        $json = $response->json();

        if (!is_array($json) || !array_is_list($json)) {
            throw new BsbException($this->errorText($response));
        }

        $out = [];
        $found = array_values(array_filter($json, fn ($r) => is_array($r) && ($r['found'] ?? true)));
        foreach ($this->statusRows($found) ?? [] as $row) {
            $out[$row['ack']] = $row;
        }

        return $out;
    }

    // ------------------------------------------------------------- Plumbing

    protected function credentials(): array
    {
        return ['api_key' => $this->apiKey, 'api_secret' => $this->apiSecret];
    }

    /**
     * Rows of a delivery status answer, or null if the answer isn't a list of them.
     *
     * @return array<int, array{ack: string, status: ?string, recipient: ?string}>|null
     */
    protected function statusRows($json): ?array
    {
        if (!is_array($json) || !array_is_list($json)) {
            return null;
        }

        $rows = [];
        foreach ($json as $row) {
            if (!is_array($row) || !isset($row['ack']) || $row['ack'] === '') {
                // A list holding an error object instead of messages.
                if (is_array($row) && (isset($row['message']) || isset($row['error']))) {
                    return null;
                }
                continue;
            }
            $status = strtoupper(trim((string) ($row['opstatus'] ?? '')));
            $rows[] = [
                'ack' => (string) $row['ack'],
                'status' => $status !== '' ? mb_substr($status, 0, 12) : null,
                'recipient' => isset($row['recipient']) ? mb_substr(preg_replace('/[^\d+]/', '', (string) $row['recipient']), 0, 32) : null,
            ];
        }

        return $rows;
    }

    /** A JSON answer's single object, whether BSB wrapped it in a list or not. */
    protected function first($json): array
    {
        if (is_array($json) && array_is_list($json)) {
            $json = $json[0] ?? [];
        }

        return is_array($json) ? $json : [];
    }

    /** The readable error in an answer: its message field, or the text itself. */
    protected function errorText(Response $response): string
    {
        $json = $response->json();
        $first = is_array($json) && array_is_list($json) ? ($json[0] ?? null) : $json;

        if (is_string($first) && trim($first) !== '') {
            return trim($first);
        }
        if (is_array($first)) {
            foreach (['message', 'error', 'msg', 'status_message'] as $key) {
                if (is_string($first[$key] ?? null) && trim($first[$key]) !== '') {
                    return trim($first[$key]);
                }
            }
        }

        $text = trim(strip_tags($response->body()));

        return $text !== '' ? mb_substr($text, 0, 300) : 'BSB answered with HTTP ' . $response->status();
    }

    protected function post(string $path, array $body): Response
    {
        // Built through the Http factory so Http::fake() still applies in tests.
        $this->client ??= Http::buildClient();

        try {
            $response = Http::setClient($this->client)
                ->connectTimeout(15)
                ->timeout(90)
                ->retry(3, 1000, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->acceptJson()
                ->asJson()
                ->post($this->base . $path, $body);
        } catch (ConnectionException $e) {
            throw new BsbException('Could not reach BSB: ' . $e->getMessage());
        }

        if ($response->serverError()) {
            throw new BsbException($this->errorText($response));
        }

        return $response;
    }
}
