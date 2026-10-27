# famexus-gateway

Famexus Gateway. Login, users, families, roles and permissions (ACL). This is the only backend the React app talks to. It authenticates users with a Sanctum cookie session, then for every request it forwards to a service it signs a short-lived RS256 JWT carrying `user_id`, `family_id`, `role` and `permissions`.

Laravel 13 on PHP 8.5, MySQL 8 (database `famexus_gateway`), Redis for queues and cache.

## Run it

The usual way is through [famexus-infra](../famexus-infra), which starts every service, MySQL, Redis and Mailpit with docker-compose.

To run this service on its own:

```sh
composer setup          # composer install, copy .env, generate key, migrate
composer dev            # serves on http://localhost:8000
```

Without the infra stack you need MySQL and Redis yourself, or set `DB_CONNECTION=sqlite` and `QUEUE_CONNECTION=sync` / `CACHE_STORE=file` in `.env`.

Check it is up:

```sh
curl http://localhost:8000/up
curl http://localhost:8000/api/v1/ping
```

## JWT keys

The gateway signs requests to the services with an RSA key pair. Generate one for local development:

```sh
mkdir -p storage/keys
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out storage/keys/jwt-private.pem
openssl rsa -in storage/keys/jwt-private.pem -pubout -out storage/keys/jwt-public.pem
```

Copy `jwt-public.pem` into `storage/keys/` of each service. Keys are git-ignored.

## Quality

```sh
composer test           # Pest
composer lint           # Pint (check only) + PHPStan level 6 via Larastan
composer format         # Pint, fixes formatting
```

GitHub Actions runs the same checks on every push to `main` and every pull request (`.github/workflows/ci.yml`).

## API

REST/JSON under `/api/v1`. The OpenAPI spec lives in [`docs/openapi.yaml`](docs/openapi.yaml); keep it in step with the routes.

## Roadmap

- [ ] Users: register, login, logout, password reset (Mailpit locally), email verification
- [ ] Sanctum SPA auth for `http://localhost:5173` (publish config: `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`)
- [ ] Families, members, roles (`owner`, `adult`, `child`) and permissions (`finance.view`, `todo.edit`, ...)
- [ ] JWT signer (`firebase/php-jwt`, RS256, 60s TTL) and a forwarding client for todo, calendar and wallit
- [ ] Global `family_id` scope + tests proving cross-family access fails
- [ ] 2FA (Fortify), rate limiting, audit log
- Later: Stripe billing (Cashier), plan limits as permissions
