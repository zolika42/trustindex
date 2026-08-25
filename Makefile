.DEFAULT_GOAL := help
PHP ?= php
COMPOSER ?= composer
CONSOLE := $(PHP) bin/console
PHPUNIT := $(PHP) bin/phpunit
COVERAGE_MIN ?= 90

.PHONY: help install setup serve db-reset seed test test-unit test-functional coverage cs cs-fix lint qa ci docker-up docker-down docker-logs

help: ## Show available commands
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n\nTargets:\n"} /^[a-zA-Z0-9_-]+:.*?##/ { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

install: ## Install Composer dependencies
	$(COMPOSER) install --prefer-dist --no-interaction

setup: install db-reset ## Install dependencies, migrate DB and seed demo data
	@echo "Application ready. Run: make serve"

serve: ## Run the local Symfony/PHP test server at http://127.0.0.1:8000
	$(PHP) -S 127.0.0.1:8000 -t public public/router.php

db-reset: ## Recreate local SQLite DB, run migrations and seed demo data
	rm -f var/app.db
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) app:seed-demo

seed: ## Seed demo reviews (safe to run repeatedly)
	$(CONSOLE) app:seed-demo

test: ## Run the complete PHPUnit suite
	$(PHPUNIT)

test-unit: ## Run unit tests
	$(PHPUNIT) tests/Unit

test-functional: ## Run functional and integration tests
	$(PHPUNIT) tests/Functional tests/Integration

coverage: ## Generate Clover coverage and enforce meaningful-code threshold
	XDEBUG_MODE=coverage $(PHPUNIT) --coverage-text --coverage-clover=var/coverage.xml
	$(PHP) bin/check-coverage.php var/coverage.xml $(COVERAGE_MIN)

cs: ## Check Symfony coding standards
	vendor/bin/php-cs-fixer fix --dry-run --diff --ansi

cs-fix: ## Apply coding-standard fixes
	vendor/bin/php-cs-fixer fix

lint: ## Lint container, YAML and Twig
	$(CONSOLE) lint:container
	$(CONSOLE) lint:yaml config --parse-tags
	$(CONSOLE) lint:twig templates

qa: cs lint test ## Run local quality gate

ci: ## Reproduce the main CI quality gate locally
	$(COMPOSER) validate --strict
	$(MAKE) qa
	$(MAKE) coverage

docker-up: ## Build and run the seeded demo server at http://127.0.0.1:8080
	docker compose up --build -d

docker-down: ## Stop the demo server
	docker compose down

docker-logs: ## Follow demo server logs
	docker compose logs -f app
