# 07 — Architecture Decisions

## ADR-001 — PostgreSQL instead of Elasticsearch

**Context:** The MVP needs durable storage for endpoints, events and delivery attempts, plus basic filtering and pagination.

**Decision:** Use PostgreSQL as the primary datastore.

**Why:** It provides durable relational storage, JSON-capable fields, indexing, transactions and straightforward Doctrine integration.

**Alternative:** Elasticsearch offers powerful search but adds infrastructure that is unnecessary for the MVP.

**Consequence:** The system is simpler to operate and deploy.

---

## ADR-002 — RabbitMQ instead of Kafka

**Context:** Webhook delivery requires asynchronous work processing, acknowledgements, retries and workers.

**Decision:** Use RabbitMQ through Symfony Messenger.

**Why:** RabbitMQ fits the queue-oriented delivery problem and is lightweight enough for a Dockerized MVP.

**Alternative:** Kafka is appropriate for high-throughput event streaming but would add infrastructure that this MVP does not require.

**Consequence:** The project focuses on reliable work delivery rather than event-stream processing.

---

## ADR-003 — Persist before returning 202

**Context:** Returning success before durable persistence could acknowledge a webhook and then lose it if the process fails.

**Decision:** Persist the WebhookEvent before returning `202 Accepted`.

```text
Receive
  |
Persist
  |
202 Accepted
  |
Queue
```

**Consequence:** The reliability boundary starts after successful persistence.

---

## ADR-004 — External ID for idempotency

**Context:** Providers may expose event identifiers, but not every provider necessarily provides a reliable identifier.

**Decision:** Deduplicate only when an external event ID is available.

```text
(endpoint_id, external_id)
```

**Consequence:** capn-hook does not infer duplicates from payload similarity, timestamps or hashes.

---

## ADR-005 — Separate delivery retry from message retry

**Context:** A worker can fail independently from the customer backend.

**Decision:** Keep two retry mechanisms separate.

- Messaging retry handles failures processing the RabbitMQ message.
- Delivery retry handles failed HTTP delivery to the customer backend.

**Consequence:** Infrastructure failures and customer-backend failures remain easier to reason about.

---

## ADR-006 — Retry transient failures only

**Context:** Repeating a request that returns a client error may not fix the underlying problem.

**Decision:** Automatic retries target transient failures such as HTTP 5xx, timeouts and connection failures. HTTP 4xx responses are not automatically retried.

**Consequence:** The MVP avoids repeatedly sending requests that are unlikely to succeed without a change in the receiving system.

---

## ADR-007 — Provider-specific verification is outside the MVP

**Context:** Webhook providers implement different signature verification schemes.

**Decision:** Do not implement provider-specific verification in v0.1.

**Consequence:** The MVP stays provider-agnostic. Signature verification can be added later through a dedicated abstraction.
