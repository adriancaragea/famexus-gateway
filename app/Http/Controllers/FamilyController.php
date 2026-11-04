<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    /**
     * The family with its members, and pending invitations for those who may manage it.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var FamilyMember $membership */
        $membership = $user->membership;
        $family = $membership->family;

        $data = [
            'id' => $family->id,
            'name' => $family->name,
            'members' => $family->members()->with('user')->oldest()->get()->map(fn (FamilyMember $member) => [
                'user_id' => $member->user_id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role->value,
            ])->values(),
        ];

        if ($membership->role->can('family.manage')) {
            $data['invitations'] = $family->invitations()->pending()->latest()->get()
                ->map(fn ($invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                ])->values();
        }

        return response()->json($data);
    }
}
