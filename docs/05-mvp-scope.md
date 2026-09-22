# 05 — MVP Scope

## Goal

Build a small but credible webhook reliability and observability service that can be deployed publicly within approximately one week.

## Must-have

### Infrastructure

- Symfony API
- PostgreSQL
- RabbitMQ
- Symfony Messenger
- Docker Compose
- Worker
- Environment configuration
- Basic health check

### Webhook ingestion

- Public endpoint
- Endpoint validation
- Payload size limit
- Durable persistence
- `202 Accepted` after persistence
- External ID deduplication when available

### Delivery

- Asynchronous processing
- HTTP delivery
- Delivery attempt history
- Success handling
- Transient failure handling
- Timeout handling
- Automatic retry
- Dead-letter state

### Recovery

- Manual retry
- Replay

### Dashboard

- Endpoint list
- Event list
- Basic counts
- Event detail
- Payload inspection
- Attempt history
- Retry/replay actions

### Quality

- Unit tests
- Integration tests
- Functional tests
- PHPStan
- CI/CD
- Docker image build

### Deployment

- Publicly reachable application
- Persistent database
- RabbitMQ
- HTTPS

## Explicitly out of scope

- OAuth / social login
- Organizations
- Teams and complex RBAC
- Billing
- Provider-specific signature verification
- PHP/JavaScript SDKs
- Elasticsearch
- Kafka
- Flink
- Prometheus
- Grafana
- Kubernetes
- Terraform
- Multi-region deployment
- High availability implementation
- Advanced webhook transformation
- Advanced routing
- Complex filtering
- Slack/email alerts
- Advanced analytics

## Future roadmap

### v0.2

- Provider signature verification
- Custom outbound headers
- Configurable retry policies
- Better search
- Metrics

### v0.3

- Organizations
- Team members
- API keys
- Notifications
- Event transformations
- Advanced routing

### v1.0

- Horizontal scaling
- High availability
- Advanced observability
- Provider integrations
- SDKs
