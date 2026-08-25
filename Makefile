.DEFAULT_GOAL := help
PHP ?= php
COMPOSER ?= composer
CONSOLE := $(PHP) bin/console
PHPUNIT := $(PHP) bin/phpunit
COVERAGE_MIN ?= 90
NPM ?= npm
VITEPRESS_VERSION ?= 1.6.4
VUE_VERSION ?= 3.5.21
DOCS_PORT ?= 8088
VITEPRESS := ./node_modules/.bin/vitepress

.PHONY: help install setup serve db-reset seed test test-unit test-functional coverage cs cs-fix lint qa ci docker-up docker-down docker-logs docs-deps docs-generate docs docs-dev docs-check docs-smoke docs-up docs-down docs-logs

help: ## Show available commands
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n\nTargets:\n"} /^[a-zA-Z0-9_-]+:.*?##/ { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

install: ## Install Composer dependencies
	$(COMPOSER) install --prefer-dist --no-interaction

setup: install db-reset docs ## Install dependencies, reset DB, seed demo data and rebuild docs
	@echo "Application ready. Run: make serve"

serve: ## Run the local Symfony/PHP test server at http://127.0.0.1:8000
	$(PHP) -S 127.0.0.1:8000 -t public public/router.php

db-reset: ## Recreate local SQLite DB, run migrations and seed demo data
	mkdir -p var
	rm -f var/app.db
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

docs-deps: ## Install pinned VitePress/Vue runtime locally without committing npm state
	$(NPM) install --no-save --no-package-lock --ignore-scripts --no-audit --no-fund vitepress@$(VITEPRESS_VERSION) vue@$(VUE_VERSION)

docs-generate: ## Generate repository-derived Developer Guide/Handbook/code reference
	$(PHP) bin/build-docs.php

docs: docs-generate docs-deps ## Build the complete static VitePress HTML documentation
	$(VITEPRESS) build docs

docs-dev: docs-generate docs-deps ## Run VitePress with live reload on http://127.0.0.1:8088
	$(VITEPRESS) dev docs --host 127.0.0.1 --port $(DOCS_PORT)

docs-check: docs ## Build documentation and verify required generated HTML entry points
	test -f docs/dist/index.html
	test -f docs/dist/DEVELOPER_GUIDE.html
	test -f docs/dist/DEVELOPER_HANDBOOK.html
	test -f docs/dist/code-reference/index.html

docs-smoke: docs-check ## Smoke-check important documentation content
	grep -q "Trustindex Reviews documentation" docs/dist/index.html
	grep -q "Developer guide" docs/dist/DEVELOPER_GUIDE.html
	grep -q "Generated code reference" docs/dist/code-reference/index.html

qa: cs lint test docs-smoke ## Run source, application and documentation quality gates

ci: ## Reproduce the main CI quality gate locally
	$(COMPOSER) validate --strict
	$(MAKE) qa
	$(MAKE) coverage

docker-up: docs ## Build/run the seeded demo and documentation services
	docker compose up --build -d app docs

docker-down: ## Stop the local Docker services
	docker compose down

docker-logs: ## Follow demo server logs
	docker compose logs -f app

docs-up: docs ## Serve generated documentation at http://127.0.0.1:8088
	docker compose up -d docs

docs-down: ## Stop only the documentation service
	docker compose stop docs

docs-logs: ## Follow documentation service logs
	docker compose logs -f docs
