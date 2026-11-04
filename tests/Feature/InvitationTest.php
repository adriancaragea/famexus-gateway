<?php

use App\Enums\Role;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\User;
use App\Notifications\FamilyInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->family = Family::factory()->create(['name' => 'Caragea']);
    $this->owner = User::factory()->inFamily(Role::Owner, $this->family)->create(['name' => 'Adrian']);
});

/**
 * Invite an email as the owner and return the token from the email link.
 */
function inviteAndGetToken(string $email = 'partner@example.com', string $role = 'adult'): string
{
    test()->postJson('/api/v1/auth/login', ['email' => test()->owner->email, 'password' => 'password'])->assertOk();
    test()->postJson('/api/v1/family/invitations', ['email' => $email, 'role' => $role])
        ->assertCreated();

    $token = null;
    Notification::assertSentOnDemand(FamilyInvitationNotification::class, function (FamilyInvitationNotification $n, array $channels, AnonymousNotifiable $notifiable) use ($email, &$token) {
        $token = $n->token;

        return $notifiable->routes['mail'] === strtolower($email);
    });
    test()->postJson('/api/v1/auth/logout')->assertNoContent();
    // Each real request starts fresh; in a test the guards keep the last user.
    app('auth')->forgetGuards();

    return (string) $token;
}

it('lets the owner invite someone who then joins with the chosen role', function () {
    $token = inviteAndGetToken('Partner@Example.com', 'adult');

    $this->getJson("/api/v1/invitations/{$token}")
        ->assertOk()
        ->assertJsonPath('email', 'partner@example.com')
        ->assertJsonPath('family.name', 'Caragea')
        ->assertJsonPath('invited_by', 'Adrian')
        ->assertJsonPath('role', 'adult');

    $this->postJson("/api/v1/invitations/{$token}/accept", [
        'name' => 'Partner',
        'password' => 'partner pass 12',
        'password_confirmation' => 'partner pass 12',
    ])
        ->assertCreated()
        ->assertJsonPath('family.id', $this->family->id)
        ->assertJsonPath('role', 'adult');

    $this->assertAuthenticated('web');
    expect($this->family->members()->count())->toBe(2);

    // The link works once.
    $this->getJson("/api/v1/invitations/{$token}")->assertNotFound();
});

it('emails a link to the React app', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/family/invitations', ['email' => 'kid@example.com', 'role' => 'child'])
        ->assertCreated();

    Notification::assertSentOnDemand(FamilyInvitationNotification::class, function (FamilyInvitationNotification $n, array $channels, AnonymousNotifiable $notifiable) {
        return str_starts_with($n->toMail($notifiable)->actionUrl, 'http://localhost/invite/');
    });
});

it('lists members and pending invitations on the family page', function () {
    inviteAndGetToken('kid@example.com', 'child');

    $this->actingAs($this->owner)->getJson('/api/v1/family')
        ->assertOk()
        ->assertJsonPath('members.0.name', 'Adrian')
        ->assertJsonPath('members.0.role', 'owner')
        ->assertJsonPath('invitations.0.email', 'kid@example.com');
});

it('only lets the owner invite, and never as owner', function () {
    $adult = User::factory()->inFamily(Role::Adult, $this->family)->create();

    $this->actingAs($adult)
        ->postJson('/api/v1/family/invitations', ['email' => 'x@example.com', 'role' => 'child'])
        ->assertForbidden();

    $this->actingAs($this->owner)
        ->postJson('/api/v1/family/invitations', ['email' => 'x@example.com', 'role' => 'owner'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');
});

it('refuses to invite an email that already has an account', function () {
    User::factory()->inFamily()->create(['email' => 'other@example.com']);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/family/invitations', ['email' => 'other@example.com', 'role' => 'adult'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects expired and revoked invitations', function () {
    $token = inviteAndGetToken();
    FamilyInvitation::query()->update(['expires_at' => now()->subMinute()]);
    $this->getJson("/api/v1/invitations/{$token}")->assertNotFound();

    $token = inviteAndGetToken('second@example.com');
    $invitation = FamilyInvitation::where('email', 'second@example.com')->firstOrFail();
    $this->actingAs($this->owner)->deleteJson("/api/v1/family/invitations/{$invitation->id}")->assertNoContent();
    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    $this->postJson("/api/v1/invitations/{$token}/accept", [
        'name' => 'X', 'password' => 'partner pass 12', 'password_confirmation' => 'partner pass 12',
    ])->assertNotFound();
});

it('cannot revoke another family\'s invitation', function () {
    $otherOwner = User::factory()->inFamily()->create();
    [$invitation] = FamilyInvitation::issue($otherOwner->membership->family, $otherOwner, 'x@example.com', Role::Adult);

    $this->actingAs($this->owner)->deleteJson("/api/v1/family/invitations/{$invitation->id}")->assertNotFound();
});
