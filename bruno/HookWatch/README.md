# HookWatch — Bruno collection

This is a [Bruno](https://www.usebruno.com/) collection for manually testing
the full HookWatch flow: creating an endpoint, simulating a third-party
provider sending a webhook, and inspecting/retrying/replaying the resulting
event.

Bruno collections are plain text files (`.bru`) meant to be committed to git
— no export/import step needed. Just open this folder from the Bruno app.

## Prerequisites

Follow [docs/08-local-dev-guide.md](../../docs/08-local-dev-guide.md) to get
the full stack running locally:

1. PostgreSQL + RabbitMQ (`docker compose up -d postgres rabbitmq`)
2. HookWatch API (`php -S 127.0.0.1:8000 -t public`)
3. Messenger worker (`php bin/console messenger:consume async -vv`)
4. The fake backend that receives outbound deliveries
   (`php -S 127.0.0.1:8123 -t api/tests/fixtures/e2e-backend`)

## How to use

1. Open Bruno → **Open Collection** → select this `bruno/HookWatch` folder.
2. Select the **Local** environment (top-right environment selector).
3. Run the requests in order:

   - **01 - Setup (acting as HookWatch customer)**
     - `Create Endpoint` — registers a new webhook endpoint pointing at the
       fake backend. Automatically stores the returned `public_token` into
       the environment for the next requests.
     - `List Endpoints` — sanity check.

   - **02 - Provider webhook (acting as Stripe)**
     - `Send Webhook (new event)` — simulates a third-party provider
       delivering a webhook to your endpoint's public URL. Automatically
       stores the returned `event_id`.
     - `Send Duplicate Webhook (same external_id)` — run twice to see
       deduplication in action (same `event_id` both times).

   - **03 - Admin (inspect, retry, replay)**
     - `List Events` — see all events across endpoints.
     - `Get Event Detail` — inspect payload, headers, status and delivery
       attempt history for `event_id`. Poll a couple of times to watch the
       status move `RECEIVED` → `PROCESSING` → `DELIVERED`.
     - `Retry Event` / `Replay Event` — manually trigger another delivery
       attempt.

4. Check `api/tests/fixtures/e2e-backend/requests.log` to see exactly what
   the fake backend received.

## Environment variables

Defined in `environments/Local.bru`:

| Variable | Default | Notes |
|---|---|---|
| `base_url` | `http://127.0.0.1:8000` | HookWatch API |
| `api_key` | `hookwatch-dev-key` | Sent as `X-API-Key` on admin endpoints |
| `fake_backend_url` | `http://127.0.0.1:8123` | Used as `forward_url` when creating an endpoint |
| `public_token` | _(auto-filled)_ | Set by "Create Endpoint" |
| `event_id` | _(auto-filled)_ | Set by the "Send Webhook" requests |
