# 06 — Development Plan

## Guiding principle

Build a working product as early as possible.

By the end of the delivery core, HookWatch should already be capable of receiving, persisting, delivering and retrying webhooks.

## Day 1 — Foundation

- [ ] Bootstrap Symfony project
- [ ] Configure Docker
- [ ] Add PostgreSQL
- [ ] Add RabbitMQ
- [ ] Configure Symfony Messenger
- [ ] Configure environment variables
- [ ] Create initial entities
- [ ] Create Doctrine migrations
- [ ] Add basic health endpoint

## Day 2 — Endpoint and ingestion

- [ ] Endpoint creation
- [ ] Endpoint listing
- [ ] Endpoint update/deactivation
- [ ] Public webhook URL
- [ ] Webhook ingestion controller
- [ ] Endpoint validation
- [ ] Payload size limit
- [ ] Webhook persistence
- [ ] `202 Accepted`
- [ ] External ID deduplication

## Day 3 — Asynchronous delivery

- [ ] Create `DeliverWebhookMessage`
- [ ] Create message handler
- [ ] Implement HTTP client
- [ ] Implement delivery service
- [ ] Create DeliveryAttempt
- [ ] Handle 2xx responses
- [ ] Handle HTTP errors
- [ ] Handle timeouts and connection failures

## Day 4 — Reliability

- [ ] Implement RetryPolicy
- [ ] Configure maximum attempts
- [ ] Implement backoff
- [ ] Distinguish transient and non-transient failures
- [ ] Implement DEAD_LETTER state
- [ ] Manual retry
- [ ] Replay
- [ ] Test failure scenarios

## Day 5 — Dashboard

- [ ] Event list
- [ ] Event filters
- [ ] Event detail
- [ ] Payload display
- [ ] Attempt history
- [ ] Endpoint management UI
- [ ] Basic event counts
- [ ] Retry/replay actions

## Day 6 — Quality

- [ ] Unit tests
- [ ] Integration tests
- [ ] Functional tests
- [ ] PHPStan
- [ ] Code quality checks
- [ ] CI pipeline
- [ ] Docker image build
- [ ] Basic security review

## Day 7 — Deployment and presentation

- [ ] Select deployment provider
- [ ] Deploy API
- [ ] Deploy worker
- [ ] Configure PostgreSQL
- [ ] Configure RabbitMQ
- [ ] Configure HTTPS
- [ ] Verify end-to-end flow
- [ ] Write README
- [ ] Add architecture diagrams
- [ ] Document architecture decisions
- [ ] Prepare public demo

## Cut rule

If time becomes limited, preserve:

1. ingestion
2. persistence
3. asynchronous delivery
4. retry
5. event history
6. tests
7. deployment

Reduce dashboard polish before reducing reliability functionality.

## Dependency order

```text
Infrastructure
    |
    v
Domain model
    |
    v
Webhook ingestion
    |
    v
Messaging
    |
    v
Delivery
    |
    v
Retry / Dead letter
    |
    v
Replay / Manual retry
    |
    v
Dashboard
    |
    v
Tests / CI
    |
    v
Deployment
```
