# TODO / Roadmap

This file tracks what's already working in HookWatch and what's still open,
so anyone who wants to contribute has a clear picture of where to jump in.

See [docs/05-mvp-scope.md](docs/05-mvp-scope.md) for the full product scope
and [docs/06-development-plan.md](docs/06-development-plan.md) for the
original day-by-day plan this list is derived from.

Feel free to open a PR for any item below. If you're picking up something
non-trivial, consider opening an issue first to avoid duplicate work.

## ✅ Done

- [x] Symfony API scaffolded (Doctrine, Messenger, Validator, Security)
- [x] Docker Compose infra (PostgreSQL, RabbitMQ)
- [x] Doctrine entities: `User`, `WebhookEndpoint`, `WebhookEvent`, `DeliveryAttempt`
- [x] Endpoint management API (create/list/get/update/delete)
- [x] Public webhook ingestion endpoint (`POST /hooks/{publicToken}`)
- [x] Payload size limit
- [x] Durable persistence before responding `202 Accepted`
- [x] External ID deduplication
- [x] Asynchronous delivery via RabbitMQ + Symfony Messenger
- [x] Delivery attempt history (`DeliveryAttempt`)
- [x] Success / transient failure / timeout handling
- [x] Automatic retry with backoff
- [x] Dead-letter state (`DEAD_LETTER`)
- [x] Manual retry (`POST /api/events/{id}/retry`)
- [x] Replay (`POST /api/events/{id}/replay`)
- [x] Provider-retry recovery: re-delivering a duplicate webhook while the
      existing event is `FAILED`/`DEAD_LETTER` triggers a new delivery attempt
- [x] Basic health check endpoint
- [x] Unit/integration test for the core MVP flow
- [x] Realistic end-to-end test against a fake local backend
- [x] Local dev docs (fully dockerized + hybrid setup, manual curl/Bruno testing)

## 🚧 Open / Good first contributions

### Reliability & correctness

- [ ] Configurable retry policy (max attempts, backoff strategy) instead of
      hardcoded values in `WebhookDeliveryService`
- [ ] Distinguish transient vs. non-transient failures more precisely
      (e.g. 4xx other than 408/429 should probably not retry at all)
- [ ] Provider signature verification (e.g. HMAC) for inbound webhooks
- [ ] Configurable outbound headers per endpoint

### Dashboard / UI

- [ ] Basic web dashboard (endpoint list, event list with filters, event
      detail with payload + attempt history, retry/replay buttons)
- [ ] Basic event counts / stats view

### Quality & CI

- [ ] PHPStan integration (static analysis)
- [ ] GitHub Actions CI pipeline (tests + PHPStan on PR)
- [ ] Docker image build in CI
- [ ] More edge-case tests (payload too large, inactive endpoint, malformed
      JSON, concurrent duplicate requests)

### Deployment

- [ ] Deploy API + worker to a public host
- [ ] HTTPS termination
- [ ] Persistent managed PostgreSQL / RabbitMQ (or equivalent) for the
      public deployment
- [ ] Architecture diagram(s) in `docs/`

### Nice to have / v0.2+ (see docs/05-mvp-scope.md)

- [ ] API keys per endpoint (instead of a single global `API_KEY`)
- [ ] Organizations / team members
- [ ] Notifications (Slack/email) on repeated failures or dead-letter
- [ ] Event transformation / advanced routing
- [ ] Metrics/observability integration

## Explicitly out of scope for now

See "Explicitly out of scope" in
[docs/05-mvp-scope.md](docs/05-mvp-scope.md) — this includes things like
OAuth/social login, billing, Kafka/Elasticsearch, Kubernetes, multi-region
deployment, etc. Please open a discussion first if you want to propose one
of these.
