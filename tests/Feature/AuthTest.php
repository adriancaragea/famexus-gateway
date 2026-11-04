<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('signs up a new user as the owner of a new family', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Adrian',
        'email' => 'Adrian@Example.com',
        'password' => 'correct horse 42',
        'password_confirmation' => 'correct horse 42',
        'family_name' => 'Caragea',
    ])
        ->assertCreated()
        ->assertJsonPath('email', 'adrian@example.com')
        ->assertJsonPath('family.name', 'Caragea')
        ->assertJsonPath('role', 'owner')
        ->assertJsonPath('permissions', Role::Owner->permissions());

    $this->assertAuthenticated('web');
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('name', 'Adrian');
});

it('rejects a weak password and a taken email at signup', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'X',
        'email' => 'taken@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
});

it('logs in with email and password and logs out', function () {
    User::factory()->inFamily(Role::Adult)->create(['email' => 'adult@example.com']);

    $this->postJson('/api/v1/auth/login', ['email' => 'ADULT@example.com', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('role', 'adult')
        ->assertJsonPath('permissions', Role::Adult->permissions());

    $this->assertAuthenticated('web');

    $this->postJson('/api/v1/auth/logout')->assertNoContent();
    $this->assertGuest('web');
});

it('rejects a wrong password', function () {
    User::factory()->create(['email' => 'a@example.com']);

    $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest('web');
});

it('locks out after five failed logins', function () {
    User::factory()->create(['email' => 'a@example.com']);

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'password'])
        ->assertTooManyRequests();
});

it('answers 401 to /me without a session', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
    $this->get('/api/v1/me')->assertUnauthorized();
});

it('resets a forgotten password through the emailed link', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'a@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'a@example.com'])->assertOk();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, &$token) {
        $token = $notification->token;
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, 'http://localhost/reset-password?token=');
    });

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'a@example.com',
        'password' => 'brand new pass 7',
        'password_confirmation' => 'brand new pass 7',
    ])->assertOk();

    $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'brand new pass 7'])->assertOk();
});

it('rejects a reset with a bad token', function () {
    User::factory()->create(['email' => 'a@example.com']);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'nope',
        'email' => 'a@example.com',
        'password' => 'brand new pass 7',
        'password_confirmation' => 'brand new pass 7',
    ])->assertUnprocessable();
});
