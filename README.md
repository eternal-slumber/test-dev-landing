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

## Runtime AI prompts

Это системные сообщения, которые приложение действительно передаёт в
OpenRouter из
[`OpenAiContactClassifier`](app/Services/OpenAiContactClassifier.php).
Runtime-контракт содержит три поля: `category`, `summary` и
`suggested_reply`. Поле `sentiment` не запрашивается и в базе не хранится.

### System prompt

```text
Analyze the contact request and return ONLY a valid JSON object with exactly these keys:
{"category":"project|employment|consultation|other","summary":"...","suggested_reply":"..."}

Use the same language as the user's comment.
Keep summary within 300 characters and suggested_reply within 800 characters.
Do not invent facts, prices, deadlines, guarantees, or obligations.
Do not claim that the request or project has been accepted.
The suggested reply is only a draft for the owner.
Do not use Markdown, code fences, bullet lists, or text outside the JSON object.
```

### User prompt template

В `user` message передаётся исходный комментарий без дополнительной обёртки:

```text
{{ contact_comment }}
```

Имя, email и телефон отдельными полями в AI-запрос не входят. Пользователь
может самостоятельно указать чувствительные данные внутри комментария,
поэтому рядом с формой есть предупреждение не отправлять пароли, платёжные
данные и другую конфиденциальную информацию.

### Ожидаемый ответ

```json
{
    "category": "project",
    "summary": "Нужна разработка внутренней CRM для небольшой компании.",
    "suggested_reply": "Спасибо за обращение! Готов обсудить текущий процесс работы и требования к CRM."
}
```

Допустимые категории: `project`, `employment`, `consultation` и `other`.
Summary ограничен 300 символами, черновик ответа — 800. Черновик показывается
владельцу обращения и не отправляется пользователю автоматически.

### Проверка ответа и fallback

OpenRouter вызывается через Chat Completions API с отключённым streaming и
strict JSON Schema. Free-router выбирает провайдера с поддержкой structured
outputs, а Response Healing может исправить синтаксические ошибки JSON у
бесплатной модели.

После ответа модель дополнительно проверяется приложением:

- content должен декодироваться в JSON object;
- должны присутствовать все три строковых поля;
- `category` должна входить в разрешённый список;
- `summary` и `suggested_reply` не должны быть пустыми;
- длина строк повторно проверяется на стороне Laravel.

Timeout, ошибка соединения, HTTP `408`, `429` или `5xx` приводят к retry job.
Очередь делает до четырёх попыток с backoff `10/60/300`. После их исчерпания
классификация получает статус `fallback`, а письма отправляются без AI-блока.
Отсутствующий ключ и постоянные ошибки конфигурации (`4xx`, кроме `408` и
`429`) включают fallback сразу. Некорректный JSON также не останавливает
основной contact flow. Персональные данные и API-ключ в логи не записываются.

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

## Использование AI при разработке

AI использовался как рабочий вспомогательный инструмент: для декомпозиции ТЗ,
подготовки и итеративной доработки отдельных компонентов, ревью backend-кода,
поиска граничных сценариев и составления тестов. Он не принимал изменения
автоматически: предложения сверялись с требованиями, текущим кодом и
документацией Laravel.

Ниже приведены обощенные версии серий коротких промптов, фактически использованных при разработке.

### Frontend и UX

**Где использовался**

- hero-блок и карточки проектов;
- контактная форма и маска российского номера;
- состояния отправки и серверных ошибок;
- страница `/status`;
- адаптивное отображение и тёмная тема.

**Пример рабочего промпта**

```text
Нужно сверстать главную страницу. Ориентируйся на это текстовое описание.

В начале страницы нужен спокойный hero-блок: короткий заголовок о разработке
веб-продуктов, небольшой текст о backend на Laravel и кнопка — посмотреть
проекты. Ниже размести проекты описание которых я тебе предоставлю.

В блоке проекта размести простой пайплайн с текстовым описанием работы проекта (от формы до PostgreSQL, AI-анализа и писем).

Дальше добавь контактную форму из макета, с кратким описанием.

Добавь простой футер и продублируй оставь ссылки на GitHub, API Docs и System Status.

Должны быть соблюдены слудющие требования
Две цветовые темы, небольшие цветовые акценты и мягкие границы. Без огромных заголовков, градиентов на пол-экрана. Не добавлять лишний glow и разноцветные градиенты. Страница должна нормально выглядеть на телефоне и широком экране. Используй Laravel 13, Inertia 3, React 19, TypeScript и Tailwind CSS 4, которые уже есть в проекте. Новые UI-библиотеки не устанавливай.

Форма отправляет POST /api/contact. После 201 API подтверждает только то,
что обращение сохранено и поставлено в обработку, поэтому не пиши пользователю,
что письмо уже отправлено. Нужны отдельные понятные состояния для заполнения,
отправки, успешного принятия, ошибок 409, 422, 429, 500 и сетевого сбоя.
При 429 используй Retry-After.

```

**Что было доработано при интеграции**

- тексты приведены к фактической семантике `accepted`;
- отдельно обработаны `409`, `422`, `429`, `500` и сетевой сбой;
- `Retry-After` используется в сообщении пользователю;
- форма подключена к реальному API через Inertia `useHttp` и Wayfinder;
- маска номера реализована без зависимости и не блокирует международный ввод;
- крупная страница разделена на небольшие landing-компоненты;
- удалён неиспользуемый frontend scaffold.

### Backend review и восстановление очередей

**Где использовался**

- transactional outbox;
- Redis jobs и DB lease;
- retry/backoff;
- reconciliation зависших шагов;
- AI fallback;
- защита от повторной обработки.

**Пример рабочего промпта**

```text
Проверь, пожалуйста, восстановление очередей в contact flow. Кажется, здесь
может быть гонка между Redis retry и scheduler.

Сейчас форма в одной транзакции создаёт contact, три contact_steps и первое
outbox-сообщение. Relay отправляет ProcessContact в Redis. У job четыре попытки
с задержками 10, 60 и 300 секунд. Когда worker забирает шаг, в базе он получает
status=processing, locked_by, locked_until, а attempts увеличивается. Раз в пять
минут scheduler ищет шаги с истёкшим lease и пытается восстановить их.

Проблема в этом случае.
Третья попытка падает, а четвёртая должна запуститься только через 300 секунд. Lease к этому моменту уже истёк, поэтомуscheduler может решить, что job потеряна, хотя она просто ждёт в Redis. Если он переоткроет outbox, будет создана новая job со свежим внутренним счётчиком, но в contact_steps останется
старое значение attempts. Тогда два счётчика начнут описывать разные циклы обработки.

Проследи этот сценарий по времени и покажи, где шаг может слишком рано перейти
в failed или fallback. После этого предложи самое небольшое исправление,
которое сохранит DB ledger согласованным с новым job. Важно, чтобы scheduler
не вмешивался во время законного backoff, восстановленный job получал полный
набор своих попыток, а после окончательного отказа AI всё равно создавались
два email outbox-сообщения.

Заодно перечисли регрессионные тесты для этой гонки. Архитектуру менять
не нужно: HTTP-ответ должен остаться быстрым, а AI и SMTP — выполняться
асинхронно через transactional outbox.
```

**Что было доработано при интеграции**

- scheduler учитывает полный интервал `lease + backoff`;
- преждевременное завершение третьей попытки устранено;
- при повторной публикации DB-счётчик попыток сбрасывается вместе с новым job;
- рост `contact_steps.attempts` ограничен числом queue attempts;
- исчерпанный AI-шаг переходит в `fallback` и создаёт оба email outbox;
- для окончательно потерянных шагов оставлена ручная команда восстановления.

### Регрессионные тесты

**Где использовался**

- идемпотентность и повторные HTTP-запросы;
- атомарность contact + steps + outbox;
- временные и постоянные ошибки AI;
- независимая отправка двух писем;
- outbox recovery и reconciliation;
- trusted proxy и API error responses.

**Пример рабочего промпта**

```text
Я исправил reconciliation зависших contact jobs и хочу закрыть исходную гонку
тестами, а не просто проверить успешный ответ команды.

Напиши Pest-тест на полный сценарий восстановления. Сначала шаг должен зависнуть
в processing после двух попыток. Затем перемести время за окончание lease
и допустимого retry-окна, запусти contacts:reconcile-stale и проверь, что шаг
вернулся в pending: lock очищен, attempts сброшен, а его outbox снова доступен
relay. После публикации это уже новый Redis job, поэтому несколько его запусков
могут снова завершиться исключением. Scheduler не должен переводить шаг
в terminal status, пока у нового job остаются разрешённые retry.

Когда последняя попытка действительно исчерпана, AI-шаг должен получить
fallback, а в outbox должны появиться ровно две отдельные задачи: письмо
владельцу и подтверждение пользователю.

Нужен и обратный тест: третья попытка упала, но четвёртая ещё законно ждёт
300 секунд. В этот момент reconciliation не должна менять ни contact_step,
ни outbox.

Используй реальные ContactStep и OutboxMessage, Laravel time travel и штатные
Queue, HTTP и Mail fakes. Проверяй не только итоговый status, но и attempts,
locked_by, locked_until, failed_at, published_at и количество сообщений.
Главный критерий — оба теста должны воспроизводить ошибку на старом коде.
```

**Что было доработано при интеграции**

- ожидания привязаны к реальным статусам моделей;
- добавлены проверки не только результата, но и промежуточного DB ledger;
- временные HTTP `408`, `429`, `5xx` отделены от постоянных `4xx`;
- проверены fallback и отсутствие частично сохранённого AI-ответа;
- PostgreSQL-only сценарии вынесены в отдельный integration suite;
- удалены шаблонные тесты, не проверявшие требования проекта.

### CI и окружение

**Где использовался**

- GitHub Actions для SQLite и PostgreSQL;
- воспроизводимый `composer setup`;
- Docker Compose;
- trusted proxies;
- локальная конфигурация PostgreSQL, Redis, Mailpit и OpenRouter.

**Пример рабочего промпта**

```text
В GitHub Actions падает composer setup, хотя локально проект запускается
через Docker Compose. Проверь workflow так, как будто ты впервые клонировал
репозиторий и у тебя нет моего .env, контейнеров и локального Redis.

Я хочу оставить основной CI быстрым: SQLite in-memory, array cache/session/mail
и sync queue. PostgreSQL-специфичные тесты outbox и конкурентной
идемпотентности должны идти отдельным job с PostgreSQL 17. Посмотри, не пытается
ли основной job подключаться к DB_HOST=postgres или использовать другую
зависимость, которая существует только внутри Compose.

Ещё сборка Vite не должна скачивать шрифты или другие ресурсы из интернета.
Если внешний CDN медленно отвечает или недоступен, npm run build всё равно
должен завершаться.

После исправления сверь версии PHP в Composer, Docker и Actions, переменные
из .env.example с compose.yaml и настройки trusted proxy. PostgreSQL, Redis
и Mailpit не должны публиковаться на всех интерфейсах, а ключ OpenRouter
и локальный .env не должны попасть в Git.

Для каждой найденной проблемы напиши коротко: почему она проявляется только
в чистом окружении, что именно нужно изменить и какой командой это проверить.
Не добавляй новые сервисы в основной CI, если SQLite уже покрывает эти тесты.
```

**Что было доработано при интеграции**

- CI разделён на быстрый SQLite suite и PostgreSQL integration job;
- Bunny Fonts удалён из build-time цепочки, сборка работает без сети;
- `.env.example` синхронизирован с Compose и разбит по разделам;
- Mailpit закреплён на конкретной версии и опубликован на localhost;
- trusted proxy задаётся явным списком адресов из окружения;
- перед коммитом запускаются Pest, Larastan, Pint, ESLint, TypeScript и Vite.
