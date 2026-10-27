<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    openssl_pkey_export($key, $privateKey);
    $this->publicKey = openssl_pkey_get_details($key)['key'];

    $privateKeyPath = tempnam(sys_get_temp_dir(), 'jwt');
    file_put_contents($privateKeyPath, $privateKey);
    config(['famexus.jwt.private_key' => $privateKeyPath]);
});

it('collects the hello message from every service', function () {
    Http::fake([
        'todo:8000/*' => Http::response(['service' => 'todo', 'message' => 'Hello from todo']),
        'calendar:8000/*' => Http::response(['service' => 'calendar', 'message' => 'Hello from calendar']),
        'wallit:8000/*' => Http::response(['service' => 'wallit', 'message' => 'Hello from finance']),
    ]);

    $this->getJson('/api/v1/hello')
        ->assertOk()
        ->assertExactJson(['services' => [
            ['service' => 'todo', 'ok' => true, 'message' => 'Hello from todo'],
            ['service' => 'calendar', 'ok' => true, 'message' => 'Hello from calendar'],
            ['service' => 'wallit', 'ok' => true, 'message' => 'Hello from finance'],
        ]]);
});

it('sends each service a signed token addressed to it', function () {
    Http::fake(['*' => Http::response(['message' => 'hi'])]);

    $this->getJson('/api/v1/hello')->assertOk();

    Http::assertSentCount(3);
    Http::assertSent(function (Request $request) {
        $token = str_replace('Bearer ', '', $request->header('Authorization')[0]);
        $claims = JWT::decode($token, new Key($this->publicKey, 'RS256'));
        $service = parse_url($request->url(), PHP_URL_HOST);

        return $claims->iss === 'famexus-gateway'
            && $claims->aud === $service
            && $claims->exp > time();
    });
});

it('reports a service that is down without failing the others', function () {
    Http::fake([
        'todo:8000/*' => Http::response(['message' => 'Hello from todo']),
        'calendar:8000/*' => Http::response(['message' => 'Invalid gateway token.'], 401),
        'wallit:8000/*' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    $this->getJson('/api/v1/hello')
        ->assertOk()
        ->assertJsonPath('services.0.ok', true)
        ->assertJsonPath('services.1', ['service' => 'calendar', 'ok' => false, 'message' => null])
        ->assertJsonPath('services.2', ['service' => 'wallit', 'ok' => false, 'message' => null]);
});
