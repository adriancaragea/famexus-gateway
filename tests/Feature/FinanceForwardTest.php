<?php

use App\Enums\Role;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('forwards to Wallit with the token Wallit expects', function (Role $role, array $permissions) {
    $user = User::factory()->inFamily($role)->create();
    Http::fake(['wallit:8000/api/v1/settings*' => Http::response(['data' => ['base_currency' => 'RON']])]);

    $this->actingAs($user)->getJson('/api/v1/finance/settings?x=1')
        ->assertOk()
        ->assertJsonPath('data.base_currency', 'RON');

    Http::assertSent(function (Request $request) use ($user, $role, $permissions) {
        $claims = JWT::decode(str_replace('Bearer ', '', $request->header('Authorization')[0]), new Key($this->jwtPublicKey, 'RS256'));

        return $request->url() === 'http://wallit:8000/api/v1/settings?x=1'
            && $request->method() === 'GET'
            && $claims->iss === 'famexus-gateway'
            && $claims->aud === 'wallit'
            && $claims->exp - $claims->iat === 60
            && $claims->user_id === $user->id
            && $claims->family_id === $user->membership->family_id
            && $claims->role === $role->value
            && $claims->permissions === $permissions;
    });
})->with([
    'owner' => [Role::Owner, Role::Owner->permissions()],
    'adult' => [Role::Adult, Role::Adult->permissions()],
    'child, without finance permissions' => [Role::Child, ['todo.view', 'todo.edit', 'calendar.view']],
]);

it('forwards the method and JSON body and returns Wallit\'s status', function () {
    $user = User::factory()->inFamily()->create();
    Http::fake(['wallit:8000/*' => Http::response(['message' => 'The base currency field is required.'], 422)]);

    $this->actingAs($user)->putJson('/api/v1/finance/settings', ['base_currency' => ''])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The base currency field is required.');

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === 'http://wallit:8000/api/v1/settings'
        && $request->data() === ['base_currency' => '']);
});

it('rewrites Wallit\'s internal pagination links to go through the gateway', function () {
    $user = User::factory()->inFamily()->create();
    Http::fake(['wallit:8000/*' => Http::response([
        'data' => [],
        'links' => ['next' => 'http://wallit:8000/api/v1/exchange-rates?page=2'],
    ])]);

    $this->actingAs($user)->getJson('/api/v1/finance/exchange-rates')
        ->assertOk()
        ->assertJsonPath('links.next', '/api/v1/finance/exchange-rates?page=2');
});

it('answers 502 when Wallit is down', function () {
    $user = User::factory()->inFamily()->create();
    Http::fake(['wallit:8000/*' => fn () => throw new ConnectionException('Connection refused')]);

    $this->actingAs($user)->getJson('/api/v1/finance/settings')->assertStatus(502);
});

it('needs a login and a family', function () {
    Http::fake();

    $this->getJson('/api/v1/finance/settings')->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson('/api/v1/finance/settings')->assertForbidden();

    Http::assertNothingSent();
});
