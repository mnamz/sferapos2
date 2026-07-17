<?php

namespace App\Services\Tangent;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TangentClient
{
    public function isConfigured(): bool
    {
        $c = config('services.tangent');

        return filled($c['base_url'] ?? null)
            && filled($c['username'] ?? null)
            && filled($c['password'] ?? null)
            && filled($c['machine_id'] ?? null);
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.tangent.enabled') && $this->isConfigured();
    }

    /**
     * Fetch (and cache) the Tangent bearer token.
     *
     * OAuth2 password grant. The vendor PDF labels this "GET" with a form body,
     * but the live endpoint only reads credentials from a POST
     * application/x-www-form-urlencoded body (verified against production:
     * GET/JSON return "unsupported_grant_type"; POST-form processes the grant).
     */
    public function token(): ?string
    {
        if (! $this->isConfigured()) {
            Log::warning('Tangent token requested while not configured.');

            return null;
        }

        $base = $this->baseUrl();
        $username = (string) config('services.tangent.username');
        $password = (string) config('services.tangent.password');
        $cacheKey = 'tangent_token_'.md5($base.'|'.$username);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post($base.'/token', [
                    'grant_type' => 'password',
                    'username' => $username,
                    'password' => $password,
                ]);

            Log::info('Tangent token response', ['status' => $response->status()]);

            if (! $response->successful()) {
                Log::error('Tangent token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $token = $response->json('access_token');
            if (! $token) {
                Log::error('Tangent token missing access_token', ['body' => $response->body()]);

                return null;
            }

            $ttl = max(60, (int) ($response->json('expires_in') ?? 1799) - 60);
            Cache::put($cacheKey, $token, $ttl);

            return $token;
        } catch (\Throwable $e) {
            Log::error('Tangent token exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * POST one day of hourly sale records to Tangent.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array{ok: bool, status: int, body: string}
     */
    public function sendSales(array $records): array
    {
        $token = $this->token();
        if (! $token) {
            return ['ok' => false, 'status' => 0, 'body' => 'Unable to obtain Tangent token'];
        }

        $payload = [
            'sales' => array_map(fn ($r) => ['sale' => $r], array_values($records)),
        ];

        Log::info('Tangent SalesHourly request', ['count' => count($records)]);

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post($this->baseUrl().'/SalesHourly', $payload);

            Log::info('Tangent SalesHourly response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $ok = $response->successful() && $response->json('status') === 'success';

            return ['ok' => $ok, 'status' => $response->status(), 'body' => $response->body()];
        } catch (\Throwable $e) {
            Log::error('Tangent SalesHourly exception', ['message' => $e->getMessage()]);

            return ['ok' => false, 'status' => 0, 'body' => $e->getMessage()];
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.tangent.base_url'), '/');
    }
}
