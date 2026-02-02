.PHONY: help init up down restart logs shell test migrate seed fresh clean

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
	cp api/.env.example api/.env
	cd api && composer install
	cd api && php artisan key:generate
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
	@echo "📧 MailHog: http://localhost:8025"

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

seed: ## Run database seeders
	docker-compose exec laravel php artisan db:seed
	@echo "✅ Database seeded"

fresh: ## Fresh migrate with seeders
	docker-compose exec laravel php artisan migrate:fresh --seed
	@echo "✅ Database refreshed"

## ===========================================
## Testing
## ===========================================

test: ## Run all tests
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

queue: ## Start queue worker
	cd api && php artisan queue:work

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
