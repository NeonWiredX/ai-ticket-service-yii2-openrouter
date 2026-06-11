# Makefile для работы с ticket-сервисом (dev).
# Бэкенд — Yii2 в backend/, окружение — docker compose (php-fpm, nginx, postgres, redis).
# Произвольные аргументы передаются через args=..., напр.: make yii args="cache/flush-all"

COMPOSE := docker compose
UID := $(shell id -u)
GID := $(shell id -g)
# Одноразовый запуск команды в php-контейнере от имени хост-юзера,
# чтобы созданные файлы (vendor и пр.) не оказались root-owned на бинд-маунте.
PHP_RUN := $(COMPOSE) run --rm --user $(UID):$(GID) -e COMPOSER_HOME=/tmp/composer php
POSTGRES_USER ?= ticket

.DEFAULT_GOAL := help

# ——— Docker ———
.PHONY: build
build: ## Собрать php-образ
	$(COMPOSE) build php

.PHONY: rebuild
rebuild: ## Пересобрать php-образ без кеша
	$(COMPOSE) build --no-cache php

.PHONY: up
up: ## Поднять стек (-d). На старте php сам ставит зависимости и катит миграции
	$(COMPOSE) up -d

.PHONY: down
down: ## Остановить стек (данные в volume сохраняются)
	$(COMPOSE) down

.PHONY: destroy
destroy: ## Остановить стек и удалить volume'ы (БД и redis будут стёрты)
	$(COMPOSE) down -v

.PHONY: restart
restart: ## Перезапустить стек
	$(COMPOSE) restart

.PHONY: ps
ps: ## Статус контейнеров
	$(COMPOSE) ps

.PHONY: logs
logs: ## Логи (follow). Один сервис: make logs s=php
	$(COMPOSE) logs -f $(s)

.PHONY: sh
sh: ## Шелл в работающем php-контейнере
	$(COMPOSE) exec php sh

# ——— Composer ———
.PHONY: install
install: ## composer install
	$(PHP_RUN) composer install

.PHONY: update
update: ## composer update
	$(PHP_RUN) composer update

.PHONY: composer
composer: ## Произвольная composer-команда: make composer args="require foo/bar"
	$(PHP_RUN) composer $(args)

# ——— Yii / миграции ———
.PHONY: yii
yii: ## Произвольная yii-команда: make yii args="cache/flush-all"
	$(PHP_RUN) php yii $(args)

.PHONY: migrate
migrate: ## Применить миграции
	$(PHP_RUN) php yii migrate --interactive=0

.PHONY: migrate-create
migrate-create: ## Создать миграцию: make migrate-create name=create_ticket_table
	@mkdir -p backend/migrations
	$(PHP_RUN) php yii migrate/create $(name) --interactive=0

# ——— Тесты ———
.PHONY: test-db
test-db: ## Создать тестовую БД ticket_test (если ещё нет)
	$(COMPOSE) exec -T postgres createdb -U $(POSTGRES_USER) ticket_test || true

.PHONY: test-build
test-build: ## Сгенерировать Tester-классы codeception
	$(PHP_RUN) vendor/bin/codecept build

.PHONY: test
test: ## Прогнать тесты: make test или make test args="unit"
	$(PHP_RUN) vendor/bin/codecept run $(args)

# ——— Доступ к данным ———
.PHONY: psql
psql: ## psql в контейнере postgres
	$(COMPOSE) exec postgres psql -U $(POSTGRES_USER) ticket

.PHONY: redis-cli
redis-cli: ## redis-cli в контейнере redis
	$(COMPOSE) exec redis redis-cli

.PHONY: help
help: ## Показать эту справку
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) \
		| awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'
