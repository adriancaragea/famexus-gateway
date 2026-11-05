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
curl http://localhost:8000/api/v1/hello   # 401 until you log in
```

## Accounts and login

- **Signup** (`POST /api/v1/auth/register`): anyone can sign up; the new user becomes the `owner` of a new family.
- **Invites**: the owner invites people by email with the `adult` or `child` role (`POST /api/v1/family/invitations`). The email links to `{FRONTEND_URL}/invite/{token}` (valid 7 days); the React app shows the invitation (`GET /api/v1/invitations/{token}`) and creates the account (`POST /api/v1/invitations/{token}/accept`). For now an email that already has an account can't be invited, because a user belongs to one family.
- **Login / logout**: email + password, Sanctum cookie session. The React app first calls `GET /sanctum/csrf-cookie`, then sends the `XSRF-TOKEN` cookie back as `X-XSRF-TOKEN`. Five failed tries per email and IP lock login for a minute.
- **No session**: signup, login, logout and accepting an invite return 400 for requests from a host not listed in `SANCTUM_STATEFUL_DOMAINS`, because Sanctum starts no session for them.
- **Password reset**: `POST /api/v1/auth/forgot-password` emails a link to `{FRONTEND_URL}/reset-password?token=...&email=...`; the app posts it to `POST /api/v1/auth/reset-password`. Locally the emails land in Mailpit (http://localhost:8025).
- **`GET /api/v1/me`**: the user with family, role and permissions.

Passwords need at least 10 characters with letters and numbers.

Roles and the permissions they grant (`app/Enums/Role.php`):

| Role | Permissions |
|---|---|
| owner | `family.manage`, `finance.view`, `finance.edit`, `todo.view`, `todo.edit`, `calendar.view`, `calendar.edit` |
| adult | same as owner, without `family.manage` |
| child | `todo.view`, `todo.edit`, `calendar.view` |

`php artisan db:seed` creates a demo family: `owner@famexus.local`, `adult@famexus.local` and `child@famexus.local`, all with the password `password`.

## Forwarding to services

`/api/v1/finance/{path}` (any method) is forwarded to Wallit as `/api/v1/{path}` with the query string and JSON body. Each forwarded request carries a 60 second RS256 JWT with `iss` `famexus-gateway`, `aud` the service name, and the member's `user_id`, `family_id`, `role` and `permissions`. Wallit's answer comes back as is, except that links to its internal address (pagination) are rewritten to `/api/v1/finance/...`. `GET /api/v1/hello` asks every service for its hello message with the same token.

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

- [x] Users: register, login, logout, password reset (Mailpit locally)
- [ ] Email verification for open signups
- [x] Sanctum SPA auth (cookie session)
- [x] Families, members, roles (`owner`, `adult`, `child`) and permissions (`finance.view`, `todo.edit`, ...)
- [x] Family invites by email
- [x] JWT signer (`firebase/php-jwt`, RS256, 60s TTL) and forwarding to wallit
- [ ] Forwarding to todo and calendar once they have endpoints
- [ ] Invites for people who already have an account (several families per user)
- [ ] Global `family_id` scope + tests proving cross-family access fails
- [ ] 2FA (Fortify), rate limiting, audit log
- Later: Stripe billing (Cashier), plan limits as permissions
