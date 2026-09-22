# HookWatch

[![CI](https://github.com/francessergi/hook-watch/actions/workflows/ci.yml/badge.svg)](https://github.com/francessergi/hook-watch/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-7.4-000000?logo=symfony&logoColor=white)
![RabbitMQ](https://img.shields.io/badge/RabbitMQ-async%20delivery-FF6600?logo=rabbitmq&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)

HookWatch is a webhook reliability and observability service that sits between third-party webhook providers and customer backends.

```mermaid
flowchart LR
    P[Third-party provider<br/>e.g. Stripe] -- POST /hooks/token --> H[HookWatch API]
    H -- 202 Accepted --> P
    H -- persist --> DB[(PostgreSQL)]
    H -- dispatch --> Q[[RabbitMQ]]
    Q --> W[Worker<br/>Messenger consumer]
    W -- deliver --> C[Customer backend]
    W -- retry / dead-letter --> DB
```

It receives webhooks, persists them before acknowledging receipt, delivers them asynchronously, retries transient failures, and provides inspection, retry, and replay capabilities.

## Event lifecycle

```mermaid
stateDiagram-v2
    [*] --> RECEIVED: webhook ingested & persisted
    RECEIVED --> PROCESSING: worker picks up message
    PROCESSING --> DELIVERED: 2xx response
    PROCESSING --> FAILED: error / timeout / non-2xx
    FAILED --> PROCESSING: automatic retry / manual retry / replay
    FAILED --> DEAD_LETTER: retries exhausted
    DEAD_LETTER --> PROCESSING: manual retry / replay
    DELIVERED --> [*]
```

## MVP stack

- Symfony
- PostgreSQL
- RabbitMQ
- Symfony Messenger
- Docker / Docker Compose
- PHPUnit
- PHPStan
- GitHub Actions

## Core capabilities

- Webhook endpoint management
- Durable webhook ingestion
- Asynchronous delivery
- Delivery attempt history
- Automatic retries with backoff
- Dead-letter state
- Manual retry
- Replay
- Deduplication when a provider supplies an external event ID

See [TODO.md](TODO.md) for what's still planned (dashboard UI, PHPStan, public deployment, etc.).

## Contributing

Want to help out? Check [TODO.md](TODO.md) for the current status and a list
of open tasks/good first contributions.

After cloning, run `git config core.hooksPath .githooks` once to enable the
repo's git hooks (currently: a `pre-commit` guard that blocks commits made
with an unintended git identity).

## Manual testing (Bruno)

A ready-to-use [Bruno](https://www.usebruno.com/) collection lives in
[`bruno/HookWatch`](bruno/HookWatch) — open that folder in Bruno to create
endpoints, simulate provider webhooks, and inspect/retry/replay events
without writing curl commands by hand. See
[bruno/HookWatch/README.md](bruno/HookWatch/README.md) for usage.

## Documentation

- [Product](docs/01-product.md)
- [Architecture](docs/02-architecture.md)
- [Domain Model](docs/03-domain-model.md)
- [API](docs/04-api.md)
- [MVP Scope](docs/05-mvp-scope.md)
- [Development Plan](docs/06-development-plan.md)
- [Architecture Decisions](docs/07-decisions.md)
- [Local Development & Manual Testing Guide](docs/08-local-dev-guide.md)

## Status

MVP foundation is implemented in the `api/` Symfony application with Doctrine entities, webhook ingestion, delivery flow, API endpoints, tests, and Docker configuration.

## Run locally

See [docs/08-local-dev-guide.md](docs/08-local-dev-guide.md) for the full guide,
including manual testing with curl/Bruno.

HookWatch runs fully dockerized. A `Makefile` wraps the common commands:

```bash
make up             # build (if needed), start Postgres/RabbitMQ/API/worker, run migrations
make fake-backend   # (separate terminal) simulate the customer service receiving deliveries
make test           # run the test suite
make help           # see all available targets
```

The API is then reachable at `http://localhost:8000`.
