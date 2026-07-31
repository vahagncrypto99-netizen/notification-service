# Шаблон для новых проектов

## 📋 Требования

- **PHP** >= 8.3
- **Composer** >= 2.0
- **Docker** и **Docker Compose** (для локальной разработки)
- **PostgreSQL** >= 15 (или через Docker)
- **Redis** >= 7 (для очередей и кеширования)

## 🛠 Установка

### 1. Клонирование репозитория

```bash
git clone sketlon-laravel-app example
cd example
```

### 2. Настройка окружения

Создайте файл `.env` в директории `main/` на основе `.env.example`:

```bash
cd main
cp .env.example .env
```

Настройте основные параметры в `.env`:

```env
APP_NAME=Jarvis
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# База данных
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=jarvis
DB_USERNAME=jarvis
DB_PASSWORD=jarvis

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Очереди
QUEUE_CONNECTION=redis
```

### 3. Запуск через Docker

```bash
# Поднять все сервисы
make up

# Или вручную
docker compose up -d
```

### 4. Установка зависимостей и настройка

```bash
# Войти в контейнер приложения
make exec app

# Или вручную
docker compose exec app zsh

# Установить зависимости
composer install

# Сгенерировать ключ приложения
php artisan key:generate

# Выполнить миграции
php artisan migrate


### 5. Запуск очередей

В отдельном терминале или через supervisor:

```bash
php artisan horizon
```

Или используйте supervisor (уже настроен в Docker):

```bash
supervisorctl start laravel-worker:*
```

## 🏗 Архитектура проекта

Проект следует принципам чистой архитектуры с разделением на слои EDA (EVENT DRIVEN DESING)

```
app/
├── Base/                    # Доменный слой
│   ├── Auth/               # Аутентификация
│   │   ├── Actions/        # Бизнес-логика
│   │   ├── Dto/            # Data Transfer Objects
│   │   └── Manager.php      # Оркестрация действий
│   └── Video/              # Генерация видео
│       ├── Actions/        # Действия (CreateVideoQueue, UpdateVideoStatus, etc.)
│       ├── Dto/            # DTO для запросов
│       ├── Events/         # События (VideoQueuedInOpenAi)
│       ├── Jobs/           # Очередные задачи
│       ├── Listeners/      # Обработчики событий
│       ├── Repository/     # Доступ к данным
│       └── Manager.php     # Оркестрация
├── Http/
│   └── Controllers/        # HTTP контроллеры
├── Models/                  # Eloquent модели
├── Policies/                # Политики доступа
├── Services/                # Внешние сервисы
│   └── VideoGenerator/     # Интеграция с OpenAI
└── Queue/                   # Базовые классы для очередей
```

### Основные компоненты

- **Manager** - Оркестрирует действия и репозитории
- **Actions** - Инкапсулируют бизнес-логику
- **Repository** - Абстракция доступа к данным
- **Policies** - Проверка прав доступа
- **DTO** - Валидация и передача данных (Spatie Laravel Data)
- **Jobs** - Асинхронная обработка задач
- **Events/Listeners** - Декoupling логики через события

## 🧪 Тестирование

### Запуск всех тестов

```bash
composer test
# или
php artisan test
```

### Запуск конкретных тестов

```bash
# Unit тесты
php artisan test --testsuite=Unit

# Feature тесты
php artisan test --testsuite=Feature

# Конкретный тест
php artisan test --filter=OpenAiVideosClientTest
```

## 🐳 Docker команды

Проект использует Makefile для удобной работы с Docker:

```bash
make up              # Поднять контейнеры
make down            # Остановить контейнеры
make restart         # Перезапустить
make build           # Собрать образы
make logs            # Просмотр логов
make exec [name]     # Войти в контейнер (по умолчанию: app)
make rebuild         # Пересобрать с нуля
make volumes-clear   # Удалить volumes проекта
```

### Прямые команды Docker Compose

```bash
docker compose up -d
docker compose exec app zsh
docker compose logs -f app
```

### Очереди

Проект использует Redis для очередей. Настройки в `config/queue.php`:

- `high_priority_queue` - Высокоприоритетные задачи (генерация видео)
- `default` - Обычные задачи

### Файловое хранилище

Видео сохраняются в `storage/app/video/`. Настройки в `config/filesystems.php`.

## 🛡 Обработка ошибок

Проект использует кастомные исключения:

- `OperationError` - Общие операционные ошибки

Все ошибки логируются через alt_log->file("log_file_name")->{error/debug/exception/info/warning}

## 📦 Зависимости

### Основные

- **Laravel 12** - PHP фреймворк
- **Spatie Laravel Data** - DTO и валидация
- **Spatie Laravel Permission** - Роли и права доступа

### Разработка

- **Pest PHP** - Тестирование

## 🤝 Разработка

### Структура коммитов

Проект следует конвенциям коммитов. Примеры:

```
feat: добавлена поддержка новых размеров видео
fix: исправлена обработка ошибок при polling статуса
refactor: рефакторинг VideoManager
test: добавлены тесты для OpenAiVideosClient
docs: обновлена документация API
```

### Code Style

Проект использует PSR-12 стандарт кодирования. Для проверки:

```bash
./vendor/bin/phpcs
```

## 📄 Лицензия

MIT License

## 🔗 Полезные ссылки

- [Laravel Documentation](https://laravel.com/docs)
- [Spatie Laravel Data](https://spatie.be/docs/laravel-data)

---# notification-service
