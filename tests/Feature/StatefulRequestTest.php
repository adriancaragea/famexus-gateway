<?php

use App\Models\User;

const NO_SESSION_MESSAGE = 'This request did not come from the Famexus app, so no session was started. Check that the app\'s host is listed in SANCTUM_STATEFUL_DOMAINS.';

beforeEach(function () {
    $this->withHeaders(['Referer' => 'http://evil.example', 'Origin' => 'http://evil.example']);
});

it('refuses a signup from a host that gets no session, without creating an account', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Adrian',
        'email' => 'adrian@example.com',
        'password' => 'correct horse 42',
        'password_confirmation' => 'correct horse 42',
    ])->assertStatus(400)->assertJsonPath('message', NO_SESSION_MESSAGE);

    expect(User::count())->toBe(0);
});

it('refuses a login from a host that gets no session', function () {
    User::factory()->create(['email' => 'adrian@example.com', 'password' => 'correct horse 42']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'adrian@example.com',
        'password' => 'correct horse 42',
    ])->assertStatus(400)->assertJsonPath('message', NO_SESSION_MESSAGE);
});
