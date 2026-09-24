# Local Development & Manual Testing Guide

This guide documents how to run capn-hook locally and validate the full
ingestion → async delivery flow manually (e.g. with Bruno or curl).

capn-hook is developed and run **fully dockerized**: PostgreSQL, RabbitMQ,
the API and the Messenger worker all run as containers via Docker Compose.
This is the only supported way to run the app locally — it's the same setup
you'd use for CI or a real deployment, so there's nothing extra to install
(no PHP, no AMQP extension) beyond Docker itself.

A `Makefile` at the repo root wraps the common Docker Compose commands.

## 1. Start the stack

```bash
make up
```

This builds the `api`/`worker` images (if needed), starts:

- `postgres` on `localhost:5432`
- `rabbitmq` on `localhost:5672` (management UI on `localhost:15672`, user/pass `guest`/`guest`)
- `api` (Symfony app via `php -S`) on `localhost:8000`
- `worker` (`messenger:consume async`) consuming delivery messages from RabbitMQ

...and runs pending database migrations automatically.

The API is then reachable at `http://localhost:8000`.

> ⚠️ If you want to test outbound deliveries with a fake "customer backend"
> running on your host machine (see step 2, or the Bruno collection), the
> `api`/`worker` containers can't reach `127.0.0.1` on your host — use
> `http://host.docker.internal:<port>` as the `forward_url` instead.

## 2. Start a fake backend to receive deliveries

For manual testing without a real third-party endpoint, use the fixture
backend that logs every incoming request:

```bash
make fake-backend
```

This runs `php -S 127.0.0.1:8123 -t api/tests/fixtures/e2e-backend` on your
host machine (it's not part of the capn-hook stack — it simulates *your*
service). Received requests are appended to
`api/tests/fixtures/e2e-backend/requests.log`.

## 3. Other useful commands

| Command | Purpose |
|---|---|
| `make down` | Stop and remove containers (keeps the Postgres data volume) |
| `make build` | Rebuild the `api`/`worker` images after a `Dockerfile`/`composer.json` change |
| `make restart` | `down` + `up` |
| `make migrate` | Create the database (if needed) and run pending migrations |
| `make logs` | Follow logs from all containers |
| `make ps` | Show container status |
| `make sh` / `make worker-sh` | Open a shell in the `api`/`worker` container |
| `make test` | Run the PHPUnit test suite inside the `api` container (uses SQLite, no Postgres/RabbitMQ needed) |
| `make destroy` | Stop containers and delete the Postgres data volume (irreversible, dev data only) |

## 4. Manual testing flow (Bruno / curl)

> 💡 A ready-made Bruno collection with all the requests below (and
> auto-chaining of `public_token`/`event_id` between requests) is available
> at [`bruno/capn-hook`](../bruno/capn-hook). Open it in the Bruno app,
> select the **Local** environment, and run requests in order instead of
> writing curl commands by hand.

### 4.1 Create a webhook endpoint

```bash
curl -sS -X POST http://127.0.0.1:8000/api/endpoints \
  -H 'Content-Type: application/json' \
  -H 'X-API-Key: capn-hook-dev-key' \
  -d '{"name":"Demo provider","forward_url":"http://host.docker.internal:8123"}'
```

Response includes `public_token` and `public_url` — this is the URL you give
to the "provider" (or use directly in the next step).

### 4.2 Send a webhook

```bash
curl -sS -X POST "http://127.0.0.1:8000/hooks/<public_token>" \
  -H 'Content-Type: application/json' \
  -H 'X-Webhook-Id: ext-123' \
  -d '{"event":"invoice.created","amount":42.50,"currency":"EUR"}'
```

Expected response: `202 Accepted` with `{"status":"accepted","event_id":...}`.
The event is now persisted as `RECEIVED` and a `DeliverWebhookMessage` is
dispatched to RabbitMQ.

### 4.3 Check delivery status

```bash
curl -sS http://127.0.0.1:8000/api/events -H 'X-API-Key: capn-hook-dev-key'
```

Once the worker picks up the message, the event status should move to
`DELIVERED` (or `FAILED`/retried if the forward URL is unreachable), with a
`DeliveryAttempt` entry showing the HTTP status/response from the fake
backend.

### 4.4 Retry / replay

```bash
curl -sS -X POST http://127.0.0.1:8000/api/events/<event_id>/retry -H 'X-API-Key: capn-hook-dev-key'
curl -sS -X POST http://127.0.0.1:8000/api/events/<event_id>/replay -H 'X-API-Key: capn-hook-dev-key'
```

## 5. Environment variables reference

| Variable | Purpose | Example (docker-compose) |
|---|---|---|
| `DATABASE_URL` | PostgreSQL DSN | `postgresql://capn-hook:capn-hook@postgres:5432/capn-hook?serverVersion=16&charset=utf8` |
| `MESSENGER_TRANSPORT_DSN` | RabbitMQ DSN for async delivery | `amqp://guest:guest@rabbitmq:5672/%2f` |
| `API_KEY` | Admin API key required via `X-API-Key` header | `capn-hook-dev-key` |
| `DEFAULT_URI` | Base URL used to build `public_url` for endpoints | `http://localhost:8000` |
| `MAX_PAYLOAD_BYTES` | Max accepted webhook payload size | `1048576` |

`api/.env.test` intentionally uses `MESSENGER_TRANSPORT_DSN=sync://` and a
SQLite `DATABASE_URL` so the automated test suite runs synchronously without
needing Postgres/RabbitMQ (see `make test`, which overrides the container's
env vars accordingly).
