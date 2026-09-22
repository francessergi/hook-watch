.DEFAULT_GOAL := help
.PHONY: help up down build restart migrate logs ps sh worker-sh test fake-backend destroy

## Show available targets
help:
	@echo "HookWatch — local development (fully dockerized)"
	@echo ""
	@echo "  make up             Build (if needed) and start Postgres, RabbitMQ, API and worker"
	@echo "  make down           Stop and remove containers (keeps data volume)"
	@echo "  make build          Rebuild the api/worker images"
	@echo "  make restart        down + up"
	@echo "  make migrate        Create the database (if needed) and run pending migrations"
	@echo "  make logs           Follow logs from all containers"
	@echo "  make ps             Show container status"
	@echo "  make sh             Open a shell in the api container"
	@echo "  make worker-sh      Open a shell in the worker container"
	@echo "  make test           Run the PHPUnit test suite inside the api container"
	@echo "  make fake-backend   Start the fake customer backend on the host (127.0.0.1:8123)"
	@echo "  make destroy        Stop containers and delete the Postgres data volume"

## Build (if needed) and start the full stack
up:
	docker compose up -d --build
	$(MAKE) migrate

## Stop and remove containers (keeps the Postgres data volume)
down:
	docker compose down

## Rebuild the api/worker images
build:
	docker compose build

## Restart the full stack
restart: down up

## Create the database (if needed) and run pending migrations
migrate:
	docker compose exec api php bin/console doctrine:database:create --if-not-exists
	docker compose exec api php bin/console doctrine:migrations:migrate -n

## Follow logs from all containers
logs:
	docker compose logs -f

## Show container status
ps:
	docker compose ps

## Open a shell in the api container
sh:
	docker compose exec api sh

## Open a shell in the worker container
worker-sh:
	docker compose exec worker sh

## Run the PHPUnit test suite inside the api container (uses SQLite, no Postgres/RabbitMQ needed)
test:
	docker compose exec \
		-e APP_ENV=test \
		-e DATABASE_URL='sqlite:///%kernel.project_dir%/var/test.db' \
		-e MESSENGER_TRANSPORT_DSN=sync:// \
		api php bin/phpunit

## Start the fake customer backend on the host, simulating the service HookWatch delivers to.
## Use http://host.docker.internal:8123 as the forward_url when creating endpoints.
fake-backend:
	cd api && php -S 127.0.0.1:8123 -t tests/fixtures/e2e-backend

## Stop containers and delete the Postgres data volume (irreversible, dev data only)
destroy:
	docker compose down -v
