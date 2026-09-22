# 04 — API

## Endpoint management

```http
POST   /api/endpoints
GET    /api/endpoints
GET    /api/endpoints/{id}
PATCH  /api/endpoints/{id}
DELETE /api/endpoints/{id}
```

### Create endpoint

```json
{
  "name": "Stripe Payments",
  "forward_url": "https://myapp.com/webhooks/stripe"
}
```

The response includes the generated public webhook URL.

## Webhook ingestion

```http
POST /hooks/{public_token}
```

The endpoint accepts provider headers and body.

A provider event ID may be supplied through a provider-specific or generic external ID header. The exact header convention can be finalized during implementation.

Successful ingestion returns:

```http
202 Accepted
```

The event is acknowledged only after persistence.

Provider-specific signature validation is outside the MVP.

## Events

```http
GET /api/events
GET /api/events/{id}
```

Supported MVP filters:

```text
status
endpoint
event_type
page
```

Event detail includes:

- metadata
- payload
- headers
- status
- received timestamp
- delivery attempts

## Recovery

```http
POST /api/events/{id}/retry
POST /api/events/{id}/replay
```

Retry follows the normal delivery flow and records `MANUAL_RETRY`.

Replay deliberately re-sends the stored event and records `REPLAY`.

## Authentication

Administrative endpoints require authentication.

The exact authentication mechanism can be finalized during implementation.

Public webhook ingestion uses the endpoint's public token.

## API principles

- Thin controllers.
- Explicit HTTP semantics.
- Separate API contracts from persistence entities where practical.
- Do not expose unnecessary infrastructure details.
