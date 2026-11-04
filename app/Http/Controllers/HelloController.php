<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\ServiceToken;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Asks every downstream service for its hello message in parallel. A service
 * that is down or rejects the call is reported with ok=false instead of
 * failing the whole response.
 */
class HelloController extends Controller
{
    public function __invoke(Request $request, ServiceToken $tokens): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var FamilyMember $membership */
        $membership = $user->membership;
        $claims = $membership->claims();

        /** @var array<string, string> $services */
        $services = config('famexus.services');

        $responses = Http::pool(fn (Pool $pool) => collect($services)
            ->map(fn (string $url, string $name) => $pool->as($name)
                ->withToken($tokens->for($name, $claims))
                ->acceptJson()
                ->timeout(3)
                ->get(rtrim($url, '/').'/api/v1/hello'))
            ->all());

        return response()->json([
            'services' => collect($services)->keys()->map(function (string $name) use ($responses) {
                $response = $responses[$name] ?? null;
                $ok = $response instanceof Response && $response->successful();

                return [
                    'service' => $name,
                    'ok' => $ok,
                    'message' => $ok ? $response->json('message') : null,
                ];
            })->values(),
        ]);
    }
}
