<?php

namespace App\Services;

use Firebase\JWT\JWT;

/**
 * Signs the short-lived RS256 JWT the gateway sends with every request to a
 * downstream service. Each token is addressed to one service ("aud").
 */
class ServiceToken
{
    /**
     * @param  array<string, mixed>  $claims  Extra claims, e.g. user_id and family_id once login exists.
     */
    public function for(string $service, array $claims = []): string
    {
        $now = time();

        return JWT::encode([
            'iss' => config('famexus.jwt.issuer'),
            'aud' => $service,
            'iat' => $now,
            'exp' => $now + (int) config('famexus.jwt.ttl'),
            ...$claims,
        ], $this->privateKey(), 'RS256');
    }

    private function privateKey(): string
    {
        $path = (string) config('famexus.jwt.private_key');

        return (string) file_get_contents(str_starts_with($path, '/') ? $path : base_path($path));
    }
}
