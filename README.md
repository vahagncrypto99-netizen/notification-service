# Notification Service

Сервис уведомлений: приём через REST API, асинхронная доставка в каналы (email, telegram) с гарантией доставки и ретраями, отчёты по уведомлениям пользователя за период.

## Технологии

PHP 8.3 · Laravel 12 · PostgreSQL · RabbitMQ · Redis · Docker (compose) · Pest · PHPStan (larastan, level 6) · Pint · Sentry

## Запуск

Нужны только Docker и Docker Compose.

```bash
git clone https://github.com/vahagncrypto99-netizen/notification-service.git
# или по SSH: git clone git@github.com:vahagncrypto99-netizen/notification-service.git
cd notification-service
make setup
```

`make setup` сам создаст `.env`-файлы из примеров, соберёт образ, поднимет контейнеры (php-fpm, воркер очереди, планировщик, nginx, postgres, rabbitmq, redis), установит зависимости, сгенерирует ключ и накатит миграции.

Дальше:

- API — `http://localhost/api/...`; аутентификация s2s: `Authorization: Bearer <token>` + HMAC-подпись канонического запроса (`X-Timestamp`, `X-Nonce` — одноразовый, `X-Signature = hmac_sha256(secret, METHOD \n URI \n TIMESTAMP \n NONCE \n BODY)`), пары `token:secret` — в `API_AUTH_CLIENTS` (`main/.env`)
- Health — `http://localhost/api/health` (БД, брокер, кэш; без подписи)
- Swagger UI — `http://localhost/api/documentation` (OpenAPI 3, PHP-атрибуты swagger-php; перегенерация — `make docs`)
- Проверки качества — `make check` (тесты + PHPStan + Pint)
- Логи — `make logs`; RabbitMQ UI — `http://localhost:15672` (креды в корневом `.env`)

## Эндпоинты

Полное описание с примерами — в Swagger UI; здесь обзор:

| Метод | Путь | Назначение |
|---|---|---|
| `POST` | `/api/notifications` | Создать уведомление (`message` ≤ 500, `user_id`, `channel`: `email`/`telegram`) — создаётся в `processing`, доставка ставится в очередь |
| `GET` | `/api/notifications/{id}` | Статус уведомления (`processing` / `sent` / `failed`) |
| `GET` | `/api/notifications?user_id=…` | История пользователя с пагинацией; фильтры `status`, `channel` |
| `POST` | `/api/reports` | Запросить отчёт за период (`user_id`, `period_from`, `period_to`) — генерация асинхронная |
| `GET` | `/api/reports/{id}` | Статус готовности отчёта |
| `GET` | `/api/reports/{id}/download` | Скачать CSV готового отчёта (до готовности — 409) |
| `GET` | `/api/health` | Readiness: БД, брокер, кэш (без подписи и rate limit) |

Все JSON-ответы — в едином контракте `{success, message, payload}`, включая ошибки валидации (422), аутентификации (401) и лимита (429).

## Как проверить API

**Через Swagger UI** — `http://localhost/api/documentation` (главная страница редиректит туда же):

1. Нажмите **Authorize** и заполните:
   - `bearer_token` → `local-dev-token`
   - `request_signature` (X-Signature) → `local-dev-secret` — **вводится signing secret, не подпись**
   - `request_timestamp` и `request_nonce` — можно оставить пустыми
2. **Try it out** на любом эндпоинте → Execute.

UI сам подписывает каждый запрос: интерцептор считает HMAC канонического запроса через Web Crypto, подставляет свежий timestamp и одноразовый nonce. Значения кредов — из `API_AUTH_CLIENTS` в `main/.env` (пары `token:secret`).

**Через curl** (подпись считается по той же формуле):

```bash
TS=$(date +%s); NONCE=$(uuidgen)
BODY='{"message":"Привет","user_id":1,"channel":"email"}'
SIG=$(printf 'POST\n/api/notifications\n%s\n%s\n%s' "$TS" "$NONCE" "$BODY" | \
      openssl dgst -sha256 -hmac local-dev-secret | awk '{print $2}')

curl -X POST http://localhost/api/notifications \
  -H "Authorization: Bearer local-dev-token" \
  -H "X-Timestamp: $TS" -H "X-Nonce: $NONCE" -H "X-Signature: $SIG" \
  -H "Content-Type: application/json" -d "$BODY"
```

Доставку видно в `make logs` (воркер → лог отправки канала), статус — `GET /api/notifications?user_id=1`.

## Архитектурные решения

- `app/Base/Notification` — домен (статусы, ретраи, отчёты, события); `app/Services/Delivery` — доставка (именованные mail-сендеры с Reply-To-логикой, Telegram-клиент с троттлингом 30 msg/sec, форматтер сообщений). Связаны через `ChannelSenderInterface`: новый канал — класс + строчка в конфиге.
- Источник истины — БД. Уведомление создаётся в `processing`, доставляется джобой из RabbitMQ (5 ретраев с backoff); `sent` ставится по фактическому исходу отправки, неисправимый отказ (невалидный адрес, блокировка бота) гасит уведомление сразу, минуя ретраи. Переходы статусов — условными UPDATE, зависшие уведомления и отчёты добирают watchdog-кроны. Семантика — at-least-once: в редкой гонке передиспатча возможна повторная отправка, дубль статусов и событий исключён.
- Аутентификация — HMAC-подпись с одноразовым nonce; хранилище nonce и счётчиков лимитов — Redis, при его недоступности API отвечает ошибкой (fail-closed), а не пропускает replay.
- Очереди — RabbitMQ (durable, ack после обработки, DLX-ретраи). Redis — кэш, nonce-хранилище и счётчики rate limiting.
- Все ответы API — `{success, message, payload}`, включая 422/401/429/500. Контроллеры тонкие: DTO → Manager → ApiResponse; неожиданные сбои — лог + Sentry.
- Валидация — в Spatie-DTO (`validateAndCreate`): вход проверяется до бизнес-логики, по слоям идёт типизированный объект.
- Отчёты: генерация во временный файл + атомарный rename, `done` — только после переноса. Агрегация — один SQL по покрывающему индексу `(user_id, created_at) INCLUDE (channel, status)`.
- События `notification.sent` / `notification.failed` публикуются версионированным конвертом в fanout-exchange RabbitMQ (отдельный AMQP-канал). Публикация — best-effort: сбой логируется и репортится, доставку не блокирует; строгий outbox — в продакшн-плане.
- Инфраструктура: один процесс — один контейнер, alpine-образ ~99 MB, порты только на 127.0.0.1, non-root, `no-new-privileges`, healthcheck у каждого сервиса, отдельная тестовая БД.

## Что бы я улучшил в продакшне

1. Реальные провайдеры каналов (Telegram Bot API, ESP для писем) + идемпотентный ключ в запросах — иначе повторная доставка джобы даст дубль сообщения. Токены — в секрет-менеджер.
2. Получатели из profile-сервиса вместо заглушек от `user_id`; для почты — bounce/complaint-вебхуки и suppression-список.
3. Transactional outbox + publisher confirms, если SLA жёстче латентности watchdog'а.
4. OAuth2 client credentials / mTLS и ротация секретов при росте числа клиентов.
5. Метрики в Prometheus: глубина очередей, доля `failed`, p95 «создано → отправлено»; алёрт на рост зависших `processing`.
6. Партиционирование `notifications` по `created_at` + retention; файлы отчётов — в объектное хранилище с TTL.
7. Отдельные воркеры на очередь (доставка ≠ отчёты) с автоскейлом от глубины и DLQ.
8. CI из `make check` + сборка образа + миграции в деплой-джобе.
