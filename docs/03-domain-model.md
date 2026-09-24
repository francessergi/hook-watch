# 03 — Domain Model

## Relationships

```text
User
 |
 +-- 1:N --> WebhookEndpoint
                  |
                  +-- 1:N --> WebhookEvent
                                  |
                                  +-- 1:N --> DeliveryAttempt
```

## User

```text
id
email
password_hash
created_at
```

The MVP uses simple application authentication. Organizations, roles and teams are excluded.

## WebhookEndpoint

```text
id
user_id
name
public_token
forward_url
active
created_at
```

A user can own multiple endpoints.

## WebhookEvent

```text
id
endpoint_id
external_id
event_type
payload
headers
received_at
status
```

`payload` and `headers` are stored as JSON-capable data.

### External ID and idempotency

When a provider supplies a reliable external event ID, capn-hook uses:

```text
(endpoint_id, external_id)
```

as the deduplication key.

If no external ID is supplied, capn-hook does not attempt to infer duplicates from payloads or timestamps. Each reception is treated as a separate event.

## WebhookEvent states

```text
RECEIVED
    |
    v
PROCESSING
    |
    +-----------> DELIVERED
    |
    +-----------> FAILED
                       |
                       +---- retry ----> PROCESSING
                       |
                       +---- exhausted -> DEAD_LETTER
```

- `RECEIVED`: persisted but delivery has not started.
- `PROCESSING`: delivery is being processed.
- `DELIVERED`: customer backend accepted the delivery with a 2xx response.
- `FAILED`: latest delivery failed and automatic retry may still be possible.
- `DEAD_LETTER`: automatic retry is exhausted or failure is considered non-transient.

## DeliveryAttempt

```text
id
event_id
attempt_number
type
started_at
finished_at
http_status
response_body
error
```

Response bodies are bounded to prevent unexpectedly large database records.

## DeliveryAttempt types

```text
AUTOMATIC
MANUAL_RETRY
REPLAY
```

## Retry vs replay

Retry creates a new `DeliveryAttempt(type=MANUAL_RETRY)` after a failed delivery.

Replay creates a new `DeliveryAttempt(type=REPLAY)` for an existing stored event.

The original event remains intact.

## Domain constraints

- Endpoint must exist and be active to accept a webhook.
- Event must be persisted before a successful ingestion response.
- External ID uniqueness is scoped to an endpoint.
- Automatic retry is limited.
- 5xx responses and network failures are transient candidates.
- 4xx responses are not automatically retried.
- Manual retry remains possible after a failure.
- Payload and response sizes are bounded.
