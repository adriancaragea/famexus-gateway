<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The signed-in user must belong to a family and, if given, hold the permission:
 * `family` or `family:family.manage`.
 */
class RequireFamilyMember
{
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();
        $membership = $user instanceof User ? $user->membership : null;

        if ($membership === null) {
            return response()->json(['message' => 'You are not a member of a family.'], 403);
        }

        if ($permission !== null && ! $membership->role->can($permission)) {
            return response()->json(['message' => 'This action is not allowed for your role.'], 403);
        }

        return $next($request);
    }
}
