<?php

use App\Services\Tangent\TangentClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.tangent.enabled' => true,
        'services.tangent.base_url' => 'https://tangent.test/api',
        'services.tangent.username' => 'postestapimy',
        'services.tangent.password' => '@APITest1234',
        'services.tangent.machine_id' => '71000005',
    ]);
});

it('is enabled only when enabled flag and all credentials are present', function () {
    expect(app(TangentClient::class)->isEnabled())->toBeTrue();
    expect(app(TangentClient::class)->isConfigured())->toBeTrue();

    config(['services.tangent.machine_id' => null]);
    expect(app(TangentClient::class)->isConfigured())->toBeFalse();
    expect(app(TangentClient::class)->isEnabled())->toBeFalse();

    config(['services.tangent.machine_id' => '71000005', 'services.tangent.enabled' => false]);
    expect(app(TangentClient::class)->isEnabled())->toBeFalse();
    expect(app(TangentClient::class)->isConfigured())->toBeTrue();
});

it('fetches and caches the bearer token', function () {
    Http::fake(['*/token' => Http::response(['access_token' => 'abc', 'expires_in' => 1799], 200)]);

    $client = app(TangentClient::class);
    expect($client->token())->toBe('abc');
    expect($client->token())->toBe('abc'); // served from cache

    $tokenCalls = collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), '/token'));
    expect($tokenCalls)->toHaveCount(1);
});

it('returns null when the token request fails', function () {
    Http::fake(['*/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    expect(app(TangentClient::class)->token())->toBeNull();
});

it('sends sales wrapped in the sales/sale envelope with a bearer header', function () {
    Http::fake([
        '*/token' => Http::response(['access_token' => 'abc', 'expires_in' => 1799], 200),
        '*/SalesHourly' => Http::response(['status' => 'success', 'message' => 'ok'], 200),
    ]);

    $records = [
        ['machineid' => '71000005', 'hour' => '00'],
        ['machineid' => '71000005', 'hour' => '01'],
    ];

    $result = app(TangentClient::class)->sendSales($records);

    expect($result['ok'])->toBeTrue();
    expect($result['status'])->toBe(200);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'SalesHourly')
        && $r->hasHeader('Authorization', 'Bearer abc')
        && count($r['sales']) === 2
        && $r['sales'][0]['sale']['hour'] === '00');
});

it('reports ok=false on a 500 error without throwing', function () {
    Http::fake([
        '*/token' => Http::response(['access_token' => 'abc', 'expires_in' => 1799], 200),
        '*/SalesHourly' => Http::response(['status' => 'error', 'errors' => [['message' => 'boom']]], 500),
    ]);

    $result = app(TangentClient::class)->sendSales([['hour' => '00']]);

    expect($result['ok'])->toBeFalse();
    expect($result['status'])->toBe(500);
});
