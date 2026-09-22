# Local Development & Manual Testing Guide

This guide documents how to run HookWatch locally and validate the full
ingestion → async delivery flow manually (e.g. with Bruno or curl), including
the exact environment fixes needed to make RabbitMQ/AMQP work.

There are two supported ways to run the stack locally:

1. **Fully dockerized** (recommended, closest to production): API, worker,
   PostgreSQL and RabbitMQ all run in Docker Compose.
2. **Hybrid** (faster iteration while developing): PostgreSQL and RabbitMQ run
   in Docker, while the API and the Messenger worker run directly on the host
   with PHP's built-in server. This is useful for quick debugging but requires
   the PHP AMQP extension to be installed locally (see below).

## 1. Fully dockerized setup (recommended)

```bash
docker compose up --build
```

This starts:

- `postgres` on `localhost:5432`
- `rabbitmq` on `localhost:5672` (management UI on `localhost:15672`, user/pass `guest`/`guest`)
- `api` (Symfony app via `php -S`) on `localhost:8000`
- `worker` (`messenger:consume async`) consuming delivery messages from RabbitMQ

Run migrations once the containers are up:

```bash
docker compose exec api php bin/console doctrine:database:create --if-not-exists
docker compose exec api php bin/console doctrine:migrations:migrate -n
```

The API is then reachable at `http://localhost:8000`.

## 2. Hybrid setup (Docker infra + local PHP app)

### 2.1 Start infrastructure only

```bash
docker compose up -d postgres rabbitmq
```

### 2.2 Install the PHP AMQP extension locally

The Symfony Messenger AMQP transport requires the native `amqp` PHP
extension (backed by `librabbitmq`). Without it, the app falls back silently
and delivery messages get stuck in `PROCESSING` because nothing can consume
them.

On macOS with Homebrew PHP:

```bash
brew install rabbitmq-c
pecl install amqp
```

Then make sure the extension is loaded by adding an ini file (adjust the PHP
version path to match `php --ini`):

```bash
echo "extension=amqp.so" > /opt/homebrew/etc/php/<your-php-version>/conf.d/ext-amqp.ini
```

Verify it loaded:

```bash
php -m | grep amqp
```

### 2.3 Configure `api/.env`

Make sure `api/.env` points at the **real** local services (no placeholder
values):

```env
DATABASE_URL="postgresql://hookwatch:hookwatch@127.0.0.1:5432/hookwatch?serverVersion=16&charset=utf8"
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@127.0.0.1:5672/%2f
```

> ⚠️ **Common pitfall:** if the event stays forever in `PROCESSING`, check
> two things: (1) `MESSENGER_TRANSPORT_DSN` is a real `amqp://...` URL, not
> `sync://` and not a placeholder/masked value, and (2) the worker process is
> actually running and connected to the same RabbitMQ instance as the API.

### 2.4 Run migrations

```bash
cd api
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate -n
```

### 2.5 Start the API

```bash
cd api
php -S 127.0.0.1:8000 -t public
```

### 2.6 Start the Messenger worker (separate terminal)

```bash
cd api
php bin/console messenger:consume async -vv
```

Leave this running — it's what actually delivers webhooks to the configured
`forward_url` and moves events from `RECEIVED` → `PROCESSING` → `DELIVERED`.

### 2.7 (Optional) Start a fake backend to receive deliveries

For manual testing without a real third-party endpoint, use the fixture
backend that logs every incoming request:

```bash
cd api
php -S 127.0.0.1:8123 -t tests/fixtures/e2e-backend
```

Received requests are appended to `tests/fixtures/e2e-backend/requests.log`.

## 3. Manual testing flow (Bruno / curl)

> 💡 A ready-made Bruno collection with all the requests below (and
> auto-chaining of `public_token`/`event_id` between requests) is available
> at [`bruno/HookWatch`](../bruno/HookWatch). Open it in the Bruno app and
> select the **Local** environment to skip writing curl commands by hand.

### 3.1 Create a webhook endpoint

```bash
curl -sS -X POST http://127.0.0.1:8000/api/endpoints \
  -H 'Content-Type: application/json' \
  -H 'X-API-Key: hookwatch-dev-key' \
  -d '{"name":"Demo provider","forward_url":"http://127.0.0.1:8123"}'
```

Response includes `public_token` and `public_url` — this is the URL you give
to the "provider" (or use directly in the next step).

### 3.2 Send a webhook

```bash
curl -sS -X POST "http://127.0.0.1:8000/hooks/<public_token>" \
  -H 'Content-Type: application/json' \
  -H 'X-Webhook-Id: ext-123' \
  -d '{"event":"invoice.created","amount":42.50,"currency":"EUR"}'
```

Expected response: `202 Accepted` with `{"status":"accepted","event_id":...}`.
The event is now persisted as `RECEIVED` and a `DeliverWebhookMessage` is
dispatched to RabbitMQ.

### 3.3 Check delivery status

```bash
curl -sS http://127.0.0.1:8000/api/events -H 'X-API-Key: hookwatch-dev-key'
```

Once the worker (2.6) picks up the message, the event status should move to
`DELIVERED` (or `FAILED`/retried if the forward URL is unreachable), with a
`DeliveryAttempt` entry showing the HTTP status/response from the fake
backend.

### 3.4 Retry / replay

```bash
curl -sS -X POST http://127.0.0.1:8000/api/events/<event_id>/retry -H 'X-API-Key: hookwatch-dev-key'
curl -sS -X POST http://127.0.0.1:8000/api/events/<event_id>/replay -H 'X-API-Key: hookwatch-dev-key'
```

## 4. Environment variables reference

| Variable | Purpose | Example (local) |
|---|---|---|
| `DATABASE_URL` | PostgreSQL DSN | `postgresql://hookwatch:hookwatch@127.0.0.1:5432/hookwatch?serverVersion=16&charset=utf8` |
| `MESSENGER_TRANSPORT_DSN` | RabbitMQ DSN for async delivery | `amqp://guest:guest@127.0.0.1:5672/%2f` |
| `API_KEY` | Admin API key required via `X-API-Key` header | `hookwatch-dev-key` |
| `DEFAULT_URI` | Base URL used to build `public_url` for endpoints | `http://localhost:8000` |
| `MAX_PAYLOAD_BYTES` | Max accepted webhook payload size | `1048576` |

`api/.env.test` intentionally uses `MESSENGER_TRANSPORT_DSN=sync://` so the
automated test suite runs synchronously without needing RabbitMQ.
