# Hosts API

API для управления хостами с асинхронным переименованием.

## Требования

- PHP 8.2+
- PostgreSQL 14+ (с расширением pg_trgm)
- Laravel 11
- Redis (опционально, для кеша/сессий)

## Установка

```bash
# Клонировать репозиторий
git clone 
cd testprj

# Установить зависимости
composer install

# Скопировать конфиг
cp .env.example .env
php artisan key:generate

# Запустить миграции c созданием админа, параметры для него указать в .env
php artisan migrate --seed

```

## Docker

```bash
docker-compose up -d
```

Сервисы:

- `testprj-app` — PHP-FPM
- `testprj-nginx` — веб-сервер
- `testprj-db` — PostgreSQL
- `testprj-redis` — Redis
- `testprj-queue` — воркер очереди

## Тестирование

### Настройка тестовой БД

```bash
# Создать базу через контейнер
docker exec -it testprj-db psql -U postgres -c "CREATE DATABASE testprj_testing;"
```
На нее настоен `phpunit.xml`.

### Запуск тестов

```bash
# Все тесты
make test
# или
php artisan test
```

## API Endpoints

### Хосты

#### GET /api/hosts

Список хостов с поиском и пагинацией.

**Query параметры:**

| Параметр | Тип | Описание |
|----------|-----|----------|
| `q` | string | Поиск по hostname (ILIKE) или точному IP |
| `page[size]` | int | Размер страницы (1-100, default: 20) |
| `page[after]` | uuid | Keyset курсор — записи после этого ID |
| `page[before]` | uuid | Keyset курсор — записи до этого ID |

**Примеры:**

```bash
# Все хосты
curl http://localhost/api/hosts

# Поиск по hostname
curl "http://localhost/api/hosts?q=web"

# Поиск по IP
curl "http://localhost/api/hosts?q=10.0.1.5"

# Keyset пагинация
curl "http://localhost/api/hosts?page[size]=10&page[after]="
```

**Ответ:**

```json
{
  "data": [
    {
      "id": "uuid",
      "hostname": "web-01.example.com",
      "ip": "10.0.1.5",
      "tags": ["web", "prod"],
      "created_at": "2026-02-03T18:00:00+00:00",
      "updated_at": "2026-02-03T18:00:00+00:00"
    }
  ],
  "meta": { "per_page": 20, "has_more": false },
  "cursors": { "before": "uuid", "after": "uuid" }
}
```

#### POST /api/hosts

Создать хост.

**Body:**

```json
{
  "hostname": "web-01.example.com",
  "ip": "10.0.1.5",
  "tags": ["web", "prod"]
}
```

**Валидация:**
- `hostname` — обязательный, RFC 1123, уникальный, max 253
- `ip` — обязательный, IPv4 или IPv6
- `tags` — опциональный массив строк

**Ответ:** `201 Created`

```json
{
  "data": {
    "id": "uuid",
    "hostname": "web-01.example.com",
    "ip": "10.0.1.5",
    "tags": ["web", "prod"],
    "created_at": "...",
    "updated_at": "..."
  }
}
```

#### PATCH /api/hosts/{id}/rename

Асинхронное переименование хоста.

**Headers:**
- `Idempotency-Key: <uuid>` — **обязательный**

**Body:**

```json
{
  "new_hostname": "web-02.example.com"
}
```

**Rate Limit:** 10 запросов в минуту на хост.

**Ответ:** `202 Accepted`

```json
{
  "operation_id": "uuid"
}
```

**Идемпотентность:** повторный запрос с тем же `Idempotency-Key` вернёт тот же `operation_id`.

### Операции

#### GET /api/operations/{id}

Статус асинхронной операции.

**Ответ:** `200 OK`

```json
{
  "id": "uuid",
  "type": "rename",
  "status": "done",
  "error": null,
  "host": {
    "id": "uuid",
    "hostname": "web-02.example.com",
    "ip": "10.0.1.5",
    "tags": ["web", "prod"]
  },
  "payload": { "new_hostname": "web-02.example.com" },
  "created_at": "...",
  "updated_at": "..."
}
```

**Статусы операции:**
- `pending` — в очереди
- `processing` — выполняется
- `done` — успешно
- `failed` — ошибка (см. поле `error`)

## Очередь

Воркер обрабатывает Job'ы из таблицы `jobs`.

```bash
# Запуск воркера
php artisan queue:work database --sleep=3 --tries=3 --max-time=3600

# Docker
docker-compose up -d queue
```

**RenameHostJob:**
1. Проверяет идемпотентность (если `done` — пропускает)
2. Проверяет уникальность нового hostname
3. Переименовывает хост в транзакции
4. Ставит статус `done` или `failed`

## Логирование

### Correlation ID

Каждый HTTP-запрос получает уникальный `correlation_id`, который связывает все события запроса: контроллер, Job в очереди, внешние вызовы.

**Заголовки:**
- Можно передать свой: `X-Correlation-ID: <uuid>`
- Если не передан — генерируется автоматически
- Возвращается в ответе: `X-Correlation-ID: <uuid>`

**Формат логов:**

```
[2026-02-03 18:00:00] [527f12df-3991-4bca-b67e-3e655a868275] local.INFO: Host rename requested {"host_id":"abc"}
[2026-02-03 18:00:00] [527f12df-3991-4bca-b67e-3e655a868275] local.INFO: Operation created {"operation_id":"xyz"}
[2026-02-03 18:00:01] [527f12df-3991-4bca-b67e-3e655a868275] local.INFO: RenameHostJob started {"operation_id":"xyz"}
[2026-02-03 18:00:01] [527f12df-3991-4bca-b67e-3e655a868275] local.INFO: RenameHostJob completed {"operation_id":"xyz"}
```

**Поиск по логам:**

```bash
# Все события одного запроса
grep "527f12df-3991-4bca-b67e-3e655a868275" storage/logs/laravel.log

# Ошибки с correlation_id
grep "ERROR" storage/logs/laravel.log | grep "527f12df"
```

### Уровни логирования

| Уровень | Когда использовать |
| ------- | ------------------ |
| `debug` | Детали для отладки, SQL-запросы |
| `info` | Успешные операции, бизнес-события |
| `warning` | Подозрительное поведение, но система работает |
| `error` | Операция не выполнена, требует внимания |
| `critical` | Система может упасть, срочное исправление |

**Настройка уровня в `.env`:**

```dotenv
LOG_LEVEL=debug     # Всё (для разработки)
LOG_LEVEL=info      # info и выше (production)
LOG_LEVEL=warning   # warning, error, critical
```

## Postman

Импортировать коллекцию: `TestPrj_Hosts_API.postman_collection.json`

**Переменные:**
- `baseUrl` — http://testprj.sulfurfun.ru
- `hostId` — автоматически после создания хоста
- `operationId` — автоматически после rename

## Индексы PostgreSQL

- `hosts_hostname_trgm_idx` — GIN pg_trgm для ILIKE поиска
- `hosts_tags_gin_idx` — GIN для jsonb тегов
- `operations_status_idx` — частичный индекс для очереди (pending/processing)
- `operations_host_status_idx` — составной индекс (host_id, status)