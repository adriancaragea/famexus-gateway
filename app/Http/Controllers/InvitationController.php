<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Resources\MeResource;
use App\Models\FamilyInvitation;
use App\Models\FamilyMember;
use App\Models\User;
use App\Notifications\FamilyInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Owners invite people by email with a role; the invitee opens the link,
 * picks a name and password and joins the family. For now invitations are
 * for emails without a Famexus account, since a user belongs to one family.
 */
class InvitationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(Role::class)->except([Role::Owner])],
        ]);
        $email = Str::lower($data['email']);

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'This email already has a Famexus account.']);
        }

        /** @var User $inviter */
        $inviter = $request->user();
        /** @var FamilyMember $membership */
        $membership = $inviter->membership;
        $family = $membership->family;

        // A new invitation replaces any pending one for the same email.
        $family->invitations()->pending()->where('email', $email)->delete();

        [$invitation, $token] = FamilyInvitation::issue($family, $inviter, $email, Role::from($data['role']));

        Notification::route('mail', $email)->notify(new FamilyInvitationNotification($invitation, $token));

        return response()->json([
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ], 201);
    }

    public function destroy(Request $request, string $invitation): Response
    {
        /** @var User $user */
        $user = $request->user();
        /** @var FamilyMember $membership */
        $membership = $user->membership;

        $membership->family->invitations()->findOrFail($invitation)->delete();

        return response()->noContent();
    }

    /**
     * Public: what the invite link is for, so the app can show it before signup.
     */
    public function show(string $token): JsonResponse
    {
        $invitation = $this->pending($token);

        return response()->json([
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'family' => ['name' => $invitation->family->name],
            'invited_by' => $invitation->inviter?->name,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Public: create the invitee's account in the family and sign them in.
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = $this->pending($token);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (User::where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages(['email' => 'This email already has a Famexus account.']);
        }

        $user = DB::transaction(function () use ($invitation, $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
            ]);
            // The link reached this inbox, so the address is verified.
            $user->forceFill(['email_verified_at' => now()])->save();

            $invitation->family->members()->create(['user_id' => $user->id, 'role' => $invitation->role]);
            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new MeResource($user->load('membership.family')))->response()->setStatusCode(201);
    }

    private function pending(string $token): FamilyInvitation
    {
        $invitation = FamilyInvitation::findPendingByToken($token);

        abort_if($invitation === null, 404, 'This invitation is invalid or has expired.');

        return $invitation;
    }
}
