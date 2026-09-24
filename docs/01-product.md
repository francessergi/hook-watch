# 01 — Product

## Problem

Integrating third-party webhooks repeatedly requires similar infrastructure:

- receiving HTTP requests
- persisting events
- asynchronous processing
- retries
- failure handling
- duplicate detection
- delivery history
- inspection and replay

capn-hook provides that infrastructure as a standalone service.

## Product concept

capn-hook sits between a third-party webhook provider and a customer's backend:

```text
Provider -> capn-hook -> Customer Backend
```

The customer configures a capn-hook endpoint as the destination for provider webhooks. capn-hook accepts and persists incoming events, then forwards them asynchronously.

## Core value proposition

capn-hook gives developers visibility and reliability around webhook delivery without requiring them to implement the same receiving, persistence, retry, deduplication, history and replay infrastructure for every provider integration.

## Reliability boundary

capn-hook cannot recover a webhook that it never received because the service itself was unavailable.

Its reliability guarantee begins once capn-hook has accepted and persisted the event.

Production high availability would require redundant instances, load balancing, health checks, durable infrastructure and backups. These are outside the MVP.

## Main workflow

1. A user creates a WebhookEndpoint.
2. capn-hook generates a public webhook URL.
3. A third-party provider sends a webhook to that URL.
4. capn-hook validates the basic request.
5. capn-hook persists the event.
6. capn-hook returns `202 Accepted`.
7. The event is queued for asynchronous delivery.
8. A worker forwards it to the customer's backend.
9. Successful delivery marks the event as `DELIVERED`.
10. Transient failures trigger retries.
11. Exhausted retries result in `DEAD_LETTER`.
12. Users can manually retry or replay stored events.

## Retry vs replay

**Retry** means attempting delivery again after a failed delivery.

**Replay** means deliberately sending an existing stored event again, even if the original delivery succeeded.

The original event and its delivery history remain available.

## Target user

Developers and small engineering teams integrating external services that send webhooks.

## Product principles

- Durable before successful acknowledgement.
- Asynchronous delivery.
- Explicit failure states.
- Preserve event history.
- Keep the MVP small.
- Prefer simple infrastructure that fits the problem.
- Do not claim reliability beyond the actual failure boundary.
