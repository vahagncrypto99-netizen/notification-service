# Notification Service

Сервис уведомлений: REST API для создания уведомлений, асинхронная доставка в каналы (email, telegram) с гарантией доставки и ретраями, отчёты по уведомлениям пользователя за период.

## Технологии

PHP 8.3 · Laravel 12 · PostgreSQL · RabbitMQ · Docker (compose) · Pest · PHPStan (larastan, level 6) · Pint · Sentry

## Запуск

Нужны только Docker и Docker Compose.

```bash
git clone git@github.com:vahagncrypto99-netizen/notification-service.git
cd notification-service
make setup
```

`make setup` сам создаст `.env`-файлы из примеров, соберёт образ, поднимет контейнеры (php-fpm, воркер очереди, планировщик, nginx, postgres, rabbitmq), установит зависимости, сгенерирует ключ и накатит миграции.

Дальше:

- API — `http://localhost/api/...` (заголовок `Authorization: Bearer <токен из API_AUTH_TOKENS в main/.env>`)
- Проверки качества — `make check` (тесты + PHPStan + Pint)
- Логи — `make logs`, RabbitMQ UI — `http://localhost:15672` (креды в корневом `.env`)
