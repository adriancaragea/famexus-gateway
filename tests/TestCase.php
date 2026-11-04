<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $jwtPublicKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Requests come from the React app, so Sanctum treats them as a cookie session.
        $this->withHeader('Referer', 'http://localhost');

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($key, $privateKey);
        $this->jwtPublicKey = openssl_pkey_get_details($key)['key'];

        $privateKeyPath = (string) tempnam(sys_get_temp_dir(), 'jwt');
        file_put_contents($privateKeyPath, $privateKey);
        config(['famexus.jwt.private_key' => $privateKeyPath]);
    }
}
