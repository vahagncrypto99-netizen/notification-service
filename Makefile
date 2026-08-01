# Notification Service — управление окружением.
# Имя compose-проекта берётся из корневого .env (COMPOSE_PROJECT_NAME).
#
# Первый запуск: make setup

DC := docker compose
SHELL := /bin/bash

# Контейнер по умолчанию (если не передан второй аргумент)
CONTAINER ?= app
# Второй goal после основной цели (например: make exec worker -> "worker")
ARG := $(word 2,$(MAKECMDGOALS))

# Первый запуск проекта с нуля: env-файлы, сборка, зависимости, миграции.
# Воркер и планировщик стартуют после composer install — иначе на чистой
# машине они крутятся в crash-loop, пока не появится vendor/.
setup:
	@test -f .env || (cp .env.example .env && echo "→ создан .env")
	@test -f main/.env || (cp main/.env.example main/.env && echo "→ создан main/.env")
	@if [ "$$(uname)" = "Linux" ]; then \
		sed -i "s/^UID=.*/UID=$$(id -u)/; s/^GID=.*/GID=$$(id -g)/" .env; \
	fi
	$(DC) build
	$(DC) up -d postgres rabbitmq redis app nginx
	$(DC) exec -T app composer install
	@grep -q '^APP_KEY=.\+' main/.env || $(DC) exec -T app php artisan key:generate --force
	$(DC) exec -T app php artisan migrate --force
	$(DC) up -d
	@echo "→ Готово: http://localhost/api (RabbitMQ UI: http://localhost:15672)"

# Перегенерация OpenAPI-документации (коммитится в репозиторий)
docs:
	$(DC) exec -T app php artisan l5-swagger:generate

# Прогон всех проверок качества
check:
	$(DC) exec -T app composer test
	$(DC) exec -T app composer analyse
	$(DC) exec -T app ./vendor/bin/pint --test

up:
	$(DC) up -d

down:
	$(DC) down

restart:
	$(DC) down && $(DC) up -d

build:
	$(DC) build

stop:
	$(DC) stop

logs:
	$(DC) logs -f

ps:
	$(DC) ps

exec:
	@c=$(if $(ARG),$(ARG),$(CONTAINER)); \
	echo "→ Заходим в '$$c'"; \
	$(DC) exec $$c bash

rebuild:
	$(DC) down -v --remove-orphans
	$(DC) build --no-cache
	$(DC) up -d

# Проглатываем второй goal (например, "worker"), чтобы make не искал такую цель
%:
	@:

help:
	@echo "Notification Service:"
	@echo "  make setup           — первый запуск с нуля (env, сборка, composer, миграции)"
	@echo "  make check           — тесты + PHPStan + Pint"
	@echo "  make docs            — сгенерировать OpenAPI-документацию"
	@echo "  make up              — поднять контейнеры"
	@echo "  make down            — остановить и удалить контейнеры"
	@echo "  make restart         — перезапуск"
	@echo "  make build           — сборка образов"
	@echo "  make stop            — остановка контейнеров"
	@echo "  make logs            — просмотр логов"
	@echo "  make ps              — статус контейнеров"
	@echo "  make exec [name]     — зайти в контейнер (по умолчанию: app)"
	@echo "  make rebuild         — пересобрать с нуля (удаляет volume-ы)"
