<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\ServiceToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

/**
 * Forwards /api/v1/{prefix}/{path} to a service's /api/v1/{path}, signing a
 * token for the signed-in member. The service enforces permissions itself.
 * Links in the answer that point at the service's internal address (e.g.
 * pagination) are rewritten to go through the gateway.
 */
class ForwardController extends Controller
{
    private const FORWARDED_HEADERS = ['Accept-Language', 'If-None-Match', 'If-Match'];

    private const RETURNED_HEADERS = ['Content-Type', 'ETag', 'Location', 'Cache-Control', 'Retry-After'];

    public function __invoke(Request $request, ServiceToken $tokens, string $path = ''): Response
    {
        $service = (string) $request->route()?->defaults['service'];
        $prefix = (string) $request->route()?->defaults['prefix'];
        $baseUrl = rtrim((string) config("famexus.services.{$service}"), '/').'/api/v1';

        /** @var User $user */
        $user = $request->user();
        /** @var FamilyMember $membership */
        $membership = $user->membership;

        $headers = collect(self::FORWARDED_HEADERS)
            ->filter(fn (string $name) => $request->hasHeader($name))
            ->mapWithKeys(fn (string $name) => [$name => (string) $request->header($name)])
            ->all();

        $pending = Http::withToken($tokens->for($service, $membership->claims()))
            ->withHeaders($headers)
            ->acceptJson()
            ->timeout(15)
            ->withQueryParameters($request->query());

        $url = $baseUrl.'/'.ltrim($path, '/');
        $method = strtoupper($request->method());

        if ($request->getContent() !== '') {
            $pending->withBody($request->getContent(), (string) ($request->header('Content-Type') ?? 'application/json'));
        }

        try {
            $upstream = match ($method) {
                'GET' => $pending->get($url),
                'HEAD' => $pending->head($url),
                default => $pending->send($method, $url),
            };
        } catch (ConnectionException) {
            return response(['message' => "The {$service} service is not reachable."], 502);
        }

        // Point internal links (http://wallit:8000/api/v1/...) at the gateway route.
        $body = str_replace(
            [$baseUrl.'/', str_replace('/', '\/', $baseUrl.'/')],
            ['/api/v1/'.$prefix.'/', '\/api\/v1\/'.$prefix.'\/'],
            $upstream->body(),
        );

        $response = response($body, $upstream->status());

        foreach (self::RETURNED_HEADERS as $name) {
            if ($upstream->header($name) !== '') {
                $response->header($name, str_replace($baseUrl.'/', '/api/v1/'.$prefix.'/', $upstream->header($name)));
            }
        }

        return $response;
    }
}
