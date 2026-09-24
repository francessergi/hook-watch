# 02 — Architecture

## System overview

```text
                         +----------------------+
                         | Third-party Provider |
                         +----------+-----------+
                                    |
                                    | HTTP webhook
                                    v
                         +----------------------+
                         |    Symfony API       |
                         | WebhookController    |
                         +----------+-----------+
                                    |
                                    v
                         +----------------------+
                         |  WebhookReceiver     |
                         +----------+-----------+
                                    |
                         +----------+----------+
                         |                     |
                         v                     v
                 +-------------+       +-------------+
                 | PostgreSQL  |       |  RabbitMQ   |
                 +-------------+       +------+------+
                                             |
                                             v
                                      +-------------+
                                      |   Worker    |
                                      +------+------+
                                             |
                                             v
                                      Customer API
```

## Main components

### Symfony API

Handles:

- endpoint management
- public webhook ingestion
- event queries
- retry/replay commands
- dashboard-facing API

Controllers remain thin and delegate application behavior to services.

### PostgreSQL

Stores:

- users
- webhook endpoints
- webhook events
- delivery attempts

Webhook payloads and headers use JSON-capable database fields.

PostgreSQL is sufficient for the MVP; Elasticsearch is deliberately excluded.

### RabbitMQ

Provides asynchronous work delivery.

Symfony Messenger is used as the application messaging abstraction and RabbitMQ as the transport.

### Worker

Consumes delivery messages and calls the customer backend.

The worker records delivery attempts and applies the delivery retry policy.

## Request ingestion flow

```text
HTTP request
    |
    v
WebhookController
    |
    v
WebhookReceiver
    |
    +--> validate endpoint
    |
    +--> validate request limits
    |
    +--> check external ID
    |
    +--> persist WebhookEvent
    |
    +--> dispatch DeliverWebhookMessage
    |
    v
202 Accepted
```

The event must be persisted before the successful HTTP response is returned.

## Delivery flow

```text
RabbitMQ
   |
   v
DeliverWebhookMessageHandler
   |
   v
WebhookDeliveryService
   |
   v
WebhookHttpClient
   |
   v
Customer backend
   |
   +--> 2xx ------> DELIVERED
   |
   +--> 4xx ------> failure / no automatic retry
   |
   +--> 5xx ------> retry if attempts remain
   |
   +--> timeout --> retry if attempts remain
```

## Failure boundaries

There are two different retry concerns.

### Delivery retry

The customer backend rejected or could not receive the webhook.

Examples:

- HTTP 5xx
- timeout
- connection failure

This is controlled by capn-hook's `RetryPolicy`.

### Message processing retry

The worker itself failed while processing a RabbitMQ message.

Examples:

- unexpected exception
- temporary database failure
- worker process failure

This is handled by the messaging infrastructure and is distinct from delivery retry.

## Docker development environment

The initial development environment contains:

```text
api
worker
postgres
rabbitmq
```

Production does not need to use Docker Compose; deployment infrastructure can be selected separately.

## Scaling considerations

The MVP does not implement high availability or multi-region deployment.

A production evolution could introduce:

- multiple API instances
- multiple workers
- load balancing
- managed PostgreSQL
- durable RabbitMQ infrastructure
- health checks
- backups
- metrics and alerting
