.PHONY: help init up down restart logs shell test check-render migrate clean

# Colors
GREEN  := $(shell tput -Txterm setaf 2)
YELLOW := $(shell tput -Txterm setaf 3)
RESET  := $(shell tput -Txterm sgr0)

## Help
help: ## Show this help
	@echo ''
	@echo 'Usage:'
	@echo '  ${YELLOW}make${RESET} ${GREEN}<target>${RESET}'
	@echo ''
	@echo 'Targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  ${YELLOW}%-15s${RESET} %s\n", $$1, $$2}' $(MAKEFILE_LIST)

## ===========================================
## Installation
## ===========================================

init: ## Initialize the project (first time setup)
	@echo "🚀 Initializing project..."
	@if [ -f api/.env ]; then \
		echo "↩️  api/.env existe déjà — conservé tel quel."; \
	else \
		cp api/.env.example api/.env; \
		cd api && php artisan key:generate; \
	fi
	cd api && composer install
	@echo "✅ Project initialized! Configure your .env file then run 'make up'"

install: ## Install all dependencies
	cd api && composer install
	@echo "✅ Dependencies installed"

## ===========================================
## Docker
## ===========================================

up: ## Start all Docker containers
	docker-compose up -d
	@echo "✅ Containers started"
	@echo "📍 API: http://localhost:8000"
	@echo "📧 Mailpit: http://localhost:8025"

down: ## Stop all Docker containers
	docker-compose down
	@echo "✅ Containers stopped"

restart: ## Restart all Docker containers
	docker-compose restart
	@echo "✅ Containers restarted"

build: ## Build Docker images
	docker-compose build --no-cache
	@echo "✅ Images built"

logs: ## Show Docker logs
	docker-compose logs -f

logs-api: ## Show API logs only
	docker-compose logs -f laravel

logs-queue: ## Show queue worker logs
	docker-compose logs -f queue

## ===========================================
## Laravel
## ===========================================

shell: ## Open shell in Laravel container
	docker-compose exec laravel sh

artisan: ## Run artisan command (usage: make artisan cmd="migrate")
	docker-compose exec laravel php artisan $(cmd)

migrate: ## Run database migrations
	docker-compose exec laravel php artisan migrate
	@echo "✅ Migrations executed"

#processPhotos: ## Process existing photos (add watermarks)
#    docker-compose exec laravel php -d memory_limit=1G artisan photos:process-existing
#	@echo "✅ Process finished"


## ===========================================
## Testing
## ===========================================

test-db-up: ## Start the dedicated test Postgres (profil test)
	docker compose --profile test up -d postgres-test
	@echo "⏳ Waiting for postgres-test..."
	@until docker exec ot-postgres-test pg_isready -U postgres -d testing >/dev/null 2>&1; do sleep 1; done
	@echo "✅ postgres-test ready on 127.0.0.1:55432"

test-db-down: ## Stop the dedicated test Postgres
	docker compose --profile test down

test: test-db-up ## Run all tests (starts the test DB first)
	cd api && php artisan test
	@echo "✅ Tests completed"

test-coverage: ## Run tests with coverage
	cd api && php artisan test --coverage

lint: ## Run code linting (Pint)
	cd api && ./vendor/bin/pint
	@echo "✅ Code formatted"

lint-check: ## Check code style without fixing
	cd api && ./vendor/bin/pint --test

## ===========================================
## Development (without Docker)
## ===========================================

serve: ## Start Laravel development server
	cd api && php artisan serve
	@echo "📍 API: http://localhost:8000"

queue: ## Start queue worker (local, without Docker)
	cd api && php artisan queue:work

queue-restart: ## Restart Docker queue worker
	docker-compose restart queue
	@echo "✅ Queue worker restarted"

## ===========================================
## Cleanup
## ===========================================

clean: ## Clean temporary files
	cd api && php artisan cache:clear
	cd api && php artisan config:clear
	cd api && php artisan route:clear
	cd api && php artisan view:clear
	@echo "✅ Cache cleared"

prune: ## Remove all Docker data
	docker-compose down -v --rmi all
	@echo "✅ Docker data removed"

check-render: ## Filet de rendu : 3 parcours dans un vrai navigateur (API locale requise)
	@echo "→ Données du filet"
	@docker compose exec -T laravel php artisan render:fixtures > .render-fixtures.json
	@echo "→ Construction du front"
	@cd web && npm run build >/dev/null
	@echo "→ Parcours"
	@cd web && node scripts/check-render.js; \
		code=$$?; \
		cd .. && docker compose exec -T laravel php artisan render:fixtures --drop >/dev/null; \
		rm -f .render-fixtures.json; \
		exit $$code
