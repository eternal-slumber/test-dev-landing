# Developer Landing

Backend-сервис формы обратной связи с AI-классификацией обращений. Полный
сценарий:

`POST /api/contact → PostgreSQL transaction → outbox → Redis → AI → два email`.

API атомарно сохраняет обращение и обязательство обработать его. После этой
фиксации сбои Redis, AI или SMTP не приводят к потере данных: незавершённые
этапы повторяются без создания нового обращения. Redis остаётся обязательной
зависимостью для принятия нового запроса и выполнения очередей.

## Стек

- PHP 8.4, Laravel 13;
- PostgreSQL 17;
- Redis 7 для очереди и rate limiting;
- Mailpit для локальной проверки SMTP;
- OpenRouter через OpenAI-compatible Chat Completions API;
- Pest 4 и PHPUnit 12;
- Inertia 3, React 19, TypeScript, Tailwind CSS 4, Vite 8;
- Docker Compose.

## Локальный запуск

Нужны Docker с Compose и Node.js с npm.

```bash
cp .env.example .env
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate
npm ci
npm run build
```

После запуска доступны:

- приложение и API: `http://localhost:8000`;
- Mailpit: `http://localhost:8026`;
- OpenAPI: `http://localhost:8000/openapi.yaml`.

Для разработки фронтенда:

```bash
npm run dev
```

Остановить окружение:

```bash
docker compose down
```

Данные PostgreSQL и Redis сохраняются в Docker volumes.
Порты приложения, PostgreSQL, Redis и Mailpit публикуются только на
`127.0.0.1`.

## Переменные окружения

`.env.example` содержит рабочую локальную конфигурацию без секретов.

| Переменная               | Назначение                                     |
| ------------------------ | ---------------------------------------------- |
| `APP_HTTP_PORT`          | Порт приложения на локальной машине            |
| `FRONTEND_URL`           | Разрешённый CORS origin                        |
| `DB_*`                   | Подключение к PostgreSQL внутри Docker network |
| `REDIS_*`                | Подключение к Redis                            |
| `CONTACT_RATE_LIMIT`     | Число обращений в минуту с одного IP           |
| `CONTACT_OWNER_EMAIL`    | Получатель письма владельцу                    |
| `MAIL_*`                 | SMTP-настройки; локально используется Mailpit  |
| `OPENAI_API_KEY`         | Ключ OpenRouter; не добавляется в Git          |
| `OPENAI_MODEL`           | Модель классификатора                          |
| `OPENAI_URL`             | OpenAI-compatible Chat Completions endpoint    |
| `OPENAI_CONNECT_TIMEOUT` | Таймаут соединения с AI в секундах             |
| `OPENAI_TIMEOUT`         | Общий таймаут AI-запроса в секундах            |

Без `OPENAI_API_KEY` классификация пропускается, но основной contact flow
продолжает работать.

## Архитектура

Основной поток разделён по слоям:

```text
ContactController
    → StoreContactRequest
    → ContactService
        → ContactRepository
        → PostgreSQL transaction
            → Contact
            → ContactStep × 3
            → OutboxMessage

Outbox relay
    → ProcessContact
        → OpenAiContactClassifier
        → OutboxMessage × 2
    → SendContactOwnerEmail
    → SendContactConfirmationEmail
```

- Controller формирует HTTP-ответ.
- Form Request авторизует и валидирует входные данные.
- Service атомарно сохраняет контакт, статусы шагов и outbox-запись.
- Repository изолирует Eloquent-доступ к PostgreSQL.
- Отдельный `outbox:work` публикует committed-сообщения в Redis.
- Три queue job выполняют AI и два email независимо, с retry и DB lease.
- Scheduler каждые пять минут обнаруживает шаги с истёкшим DB lease.

Под полным циклом понимается системный сценарий обработки обращения.
HTTP-запрос завершается после надёжной фиксации обращения и обязательства
выполнить последующие этапы. AI-анализ и отправка email выполняются
асинхронно, поскольку внешний AI-провайдер может отвечать 14–30 секунд. Это
сокращает время ответа API и исключает зависимость пользовательского запроса
от длительности внешних сервисов.

Необязательный `Idempotency-Key` хранится в таблице `contacts` с
unique-ограничением. Повтор запроса с тем же ключом возвращает исходное
обращение без повторного AI-вызова и писем. Использование ключа с другими
данными возвращает `409 Conflict`. Если заголовок отсутствует, сервер создаёт
новый UUID; отдельный повтор такого запроса не дедуплицируется.

`201 Created` подтверждает создание обращения и outbox-записи, но не
гарантирует, что AI-анализ и email уже завершены.

## API

### `POST /api/contact`

Необязательный заголовок для клиентской идемпотентности:

```http
Idempotency-Key: UUID
```

Поля запроса:

| Поле      | Правила                                                              |
| --------- | -------------------------------------------------------------------- |
| `name`    | string, 2–100 символов                                               |
| `phone`   | string, 10–15 цифр, до 30 символов с учётом пробелов, `+`, `()`, `-` |
| `email`   | корректный RFC email, до 255 символов                                |
| `comment` | string, 10–3000 символов                                             |

Успешный запрос:

```bash
curl --request POST http://localhost:8000/api/contact \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "name": "Артем",
    "phone": "+7 (999) 123-45-67",
    "email": "artem@example.com",
    "comment": "Хочу обсудить разработку веб-сервиса."
  }'
```

Ответ `201 Created`:

```json
{
    "data": {
        "id": 1,
        "status": "accepted"
    },
    "message": "Обращение принято"
}
```

Пример ошибки валидации:

```bash
curl --request POST http://localhost:8000/api/contact \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "name": "А",
    "phone": "без цифр",
    "email": "wrong-email",
    "comment": "коротко"
  }'
```

Основные статусы:

- `201` — обращение принято;
- `409` — idempotency key уже использован с другими данными;
- `422` — ошибка валидации;
- `429` — превышен rate limit;
- `500` — непредвиденная внутренняя ошибка.

Все API-ошибки возвращаются в JSON. Подробный контракт и схемы находятся в
[`public/openapi.yaml`](public/openapi.yaml). Каждый API-ответ содержит
`X-Request-ID`; ответ `429` дополнительно возвращает `Retry-After` в секундах.

### `GET /api/health`

Проверяет соединения с PostgreSQL и Redis, а также наличие конфигурации AI и
mail без внешних запросов к этим сервисам:

```bash
curl --header 'Accept: application/json' http://localhost:8000/api/health
```

Исправный сервис возвращает `200 OK`; при недоступной инфраструктуре или
неполной конфигурации возвращаются `status: degraded` и HTTP `503`.

## AI-интеграция

Классификатор передаёт внешнему AI-провайдеру только текст поля `comment`.
Отдельные поля `email` и `phone` в AI-запрос не входят. При этом пользователь
может самостоятельно указать контактные или другие чувствительные данные
внутри комментария, поэтому около формы отображается предупреждение не
указывать пароли, платёжные данные и другую конфиденциальную информацию.
Допустимые категории:

- `project`;
- `employment`;
- `consultation`;
- `other`.

OpenRouter вызывается через Chat Completions API с отключённым streaming и
strict JSON Schema. Free-router выбирает только провайдера, который
поддерживает structured outputs, а Response Healing исправляет синтаксические
ошибки JSON от бесплатных моделей. AI возвращает:

```json
{
    "category": "project",
    "summary": "Краткое содержание обращения.",
    "suggested_reply": "Черновик ответа владельца."
}
```

Summary ограничен 300 символами, черновик ответа — 800. Prompt запрещает
придумывать цены, сроки, гарантии и обязательства и требует использовать язык
исходного комментария.

Timeout, ошибка соединения, HTTP `408`, `429` или `5xx` завершают job с
исключением. Очередь делает до четырёх попыток с backoff `10/60/300`. После
исчерпания попыток классификация получает статус `fallback`, а оба письма
ставятся в очередь без AI-блока. Отсутствующий ключ и постоянные ошибки
конфигурации (`4xx`, кроме `408` и `429`) сразу включают fallback, потому что
повтор без изменения конфигурации не поможет. Некорректный JSON также
обрабатывается как fallback. В логах нет персональных данных.

## Надёжность и безопасность

- rate limiting: Redis, по IP, значение задаётся `CONTACT_RATE_LIMIT`;
- trusted proxy: используется адрес клиента из proxy-заголовков;
- proxy-заголовки принимаются только от непосредственного локального proxy;
- CORS: только `FRONTEND_URL`, без wildcard;
- transactional outbox: запись контакта и фоновой задачи в одной DB-транзакции;
- relay: конкурентный `FOR UPDATE SKIP LOCKED`, lease и backoff `10/60/300`;
- после 10 ошибок публикации outbox получает `failed_at`, исключается из
  повторной обработки и записывает событие уровня `critical`;
- AI: 4 попытки для временных ошибок с backoff `10/60/300`, затем fallback;
- email: две независимые Redis-задачи, 4 попытки с backoff `10/60/300`;
- step ledger: `pending`, `processing`, `succeeded`/`fallback`/`sent`, `failed`;
- scheduler учитывает номер попытки и полный интервал `lease + backoff`;
  потерянный retryable-шаг начинает новый согласованный retry-цикл через
  outbox, а исчерпанный AI-шаг переходит в fallback и ставит оба письма в
  очередь;
- idempotency: unique UUID предотвращает повторные записи и уведомления;
- HTTP request logs: `storage/logs/requests-YYYY-MM-DD.log`, хранение 14 дней;
- логируются все запросы, обработанные Laravel; ассеты, которые nginx/Vite
  отдаёт напрямую, middleware не проходят;
- логируются request ID, method, path, IP, status, duration и `contact_id`;
- email, телефон, комментарий и API-ключ в request-log не записываются;
- production-ошибки не раскрывают stack trace и внутренние сообщения.

Failed-шаг можно вручную вернуть в очередь:

```bash
docker compose exec app php artisan contact:retry-failed 123
```

Команда сбрасывает все `failed`-шаги указанного обращения и переоткрывает
соответствующие outbox-сообщения. Уже завершённые шаги она не затрагивает.
Команда `contacts:reconcile-stale` запускается контейнером `scheduler` каждые
пять минут. Она не прерывает штатно отложенные queue retry и автоматически
восстанавливает действительно потерянную обработку после полного retry-окна.

SMTP работает с гарантией at-least-once. Редкий дубль возможен, если почтовый
сервер принял письмо, но worker завершился до записи статуса `sent`.
Exactly-once потребует idempotency API со стороны почтового провайдера.

## Хранение данных

Обращения, включая имя, email, телефон, комментарий и результат AI-анализа,
хранятся в таблице PostgreSQL `contacts`. `contact_steps` показывает результат
классификации и каждого письма, а `outbox_messages` гарантирует постановку
фоновой работы. Локально PostgreSQL хранится в Docker volume.

Предполагаемый production-срок хранения обращений — 90 дней с момента
создания. Автоматическая очистка сейчас не реализована: до появления
retention-команды записи сохраняются без автоматического удаления и при
необходимости удаляются вручную.

Сессии также используют PostgreSQL. Redis хранит очередь, rate limit и
локальный cache. Логи находятся в `storage/logs`.

## Тесты и проверки

Тесты используют SQLite in-memory, синхронную очередь и array-драйверы.
HTTP-запросы и email подменяются через Laravel fakes.
GitHub Actions запускает проверки с тем же SQLite-окружением, поэтому
PostgreSQL и Redis services для основного CI job не требуются.

Отдельный GitHub Actions job поднимает PostgreSQL 17 и проверяет транзакционный
outbox, конкурентную идемпотентность и `FOR UPDATE SKIP LOCKED`. Эти тесты
лежат в `tests/Postgres` и не входят в быстрый SQLite suite.

```bash
docker compose exec app php artisan test --compact
docker compose exec app composer run types:check
npm run lint:check
npm run types:check
npm run build
```

## Что сделано с помощью AI

AI-ассистент использовался для декомпозиции ТЗ в roadmap, подготовки
черновиков тестов и документации, а также анализа отказов AI, SMTP,
reverse proxy и повторных запросов.

Вручную проверены и скорректированы слои приложения, Laravel-конфигурация,
валидация телефона, JSON error handling, CORS, безопасное логирование,
idempotency, очередь писем и Docker Compose. Итоговый код проверяется Pest,
Larastan, ESLint, TypeScript и production-сборкой.

## Деплой

Публичный deployment URL пока не настроен. ТЗ допускает локальный запуск;
инструкция выше воспроизводит API через Docker Compose. После публикации
сюда нужно добавить HTTPS URL API и заменить локальный `server` в OpenAPI.
