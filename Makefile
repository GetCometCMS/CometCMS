SHELL := /bin/bash

.PHONY: build ci clean dev dist lint-frontend lint-php test test-backend test-frontend web-build

PHP_HOST ?= 127.0.0.1
PHP_PORT ?= 8000
VITE_HOST ?= 127.0.0.1
VITE_PORT ?= 5173
DIST ?= dist

## Start the PHP CMS and Vite UI together.
dev:
	node scripts/dev.mjs "$(PHP_HOST)" "$(PHP_PORT)" "$(VITE_HOST)" "$(VITE_PORT)"

## Build a deployment-ready folder in dist/.
build:
	node scripts/build.mjs full "$(DIST)"

## Compile the Vue admin UI into dist/admin without assembling PHP files.
web-build:
	node scripts/build.mjs admin "$(DIST)"

## Lint PHP source and backend tests.
lint-php:
	node scripts/lint-php.mjs

## Lint Vue admin source and tests.
lint-frontend:
	npm --workspace web run lint

## Run backend PHP tests.
test-backend:
	php tests/php/run.php

## Run frontend Vue/Vite unit tests.
test-frontend:
	npm --workspace web run test

## Run all tests that should pass before shipping.
test: lint-php lint-frontend test-backend test-frontend

## Full CI verification: tests plus production build.
ci: test build

## Backwards-compatible alias for the deployment build.
dist: build

## Remove generated build output.
clean:
	node scripts/build.mjs clean "$(DIST)"
