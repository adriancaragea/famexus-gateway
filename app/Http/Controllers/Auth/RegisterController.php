<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\Family;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Open signup: the new user becomes the owner of a new family.
 */
class RegisterController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'family_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
            ]);

            $family = Family::create(['name' => $data['family_name'] ?? $data['name'].'\'s family']);
            $family->members()->create(['user_id' => $user->id, 'role' => Role::Owner]);

            return $user;
        });

        event(new Registered($user));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new MeResource($user->load('membership.family')))->response()->setStatusCode(201);
    }
}
