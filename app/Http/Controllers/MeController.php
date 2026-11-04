<?php

namespace App\Http\Controllers;

use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Always 200, also right after signup when the model was just created.
        return (new MeResource($user->load('membership.family')))->response()->setStatusCode(200);
    }
}
