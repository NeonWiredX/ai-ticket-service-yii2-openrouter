# AI Ticket Classifier

Сервис классификации обращений в поддержку: принимает тикет, прогоняет через AI-модель
(категория / приоритет / риск / предлагаемый маршрут), накладывает детерминированную политику
(можно ли обрабатывать автоматически и куда направить) и сохраняет **воспроизводимое аудит-решение**.

Ключевые свойства:

- **Идемпотентный приём** — дубль по `(tenant_id, external_id)` не плодит записей.
- **Версионированные контракты** — у каждого решения зафиксированы `schema_version`, `policy_version`,
  `prompt_version` и `model`: классификацию можно воспроизвести/сравнить после смены модели или промпта.
- **Сменный классификатор за интерфейсом** — `FakeClassifier` (детерминированный демо/CI) ↔
  `OpenRouterClassifier` (реальная модель через [OpenRouter](https://openrouter.ai), structured output).
- **Невалидный ответ модели — не падение, а аудит** — решение сохраняется со статусом `failed`,
  политика отправляет его в ручную сортировку.
- **Два входа поверх одного домена** — CLI (`yii ticket/process`) и HTTP (`POST ticket/add`),
  одинаковый стабильный JSON-результат.

Стек: PHP **8.5**, Yii2 **2.0.55**, PostgreSQL **18**, Redis **8**, dev-окружение на docker compose.

---

## Pipeline

```mermaid
flowchart TD
    IN["Тикет&nbsp;&nbsp;(JSON-файл / HTTP-тело)"] --> ING

    subgraph PROC["TicketProcessingService"]
        ING["TicketIngestionService<br/><i>upsert ON CONFLICT по (tenant_id, external_id)</i>"]
        ING --> GUARD{"Решение по тикету<br/>уже есть?"}
        GUARD -->|"да (повтор)"| SKIP["classification_skipped = true"]
        GUARD -->|"нет (новый / краш-рекавери)"| CLS

        subgraph DOMAIN["TicketClassificationService (домен, без БД)"]
            CLS["Classifier<br/>schema + prompt → модель → ClassificationResultDto"]
            CLS --> POL["PolicyV1Service<br/>allow / require_approval / block + финальный маршрут"]
        end

        POL --> PERSIST["сохранить AiDecision"]
        SKIP --> RES
        PERSIST --> RES["TicketProcessingResult"]
    end

    RES --> OUT["Стабильный JSON&nbsp;&nbsp;(stdout / HTTP-ответ)"]
```

**Слои:**

| Компонент | Роль |
|---|---|
| `TicketIngestionService` | Приём: валидация, идемпотентный `INSERT … ON CONFLICT DO NOTHING`, возврат `{ticket, wasCreated}` |
| `TicketClassifierInterface` | Граница классификатора: `FakeClassifier` / `OpenRouterClassifier` |
| `ClassificationSchemaV1` | Версионированный контракт ответа модели: `getSchema()` → structured output наружу, `parse()` → валидация ответа и сборка DTO |
| `ClassificationPromptV1` | Версионированный промпт (system + рендер тикета в user-сообщение) |
| `PolicyV1Service` | Детерминированные правила маршрутизации поверх классификации |
| `TicketClassificationService` | Оркестрация classify + policy → `AiDecisionDto` (чистый домен, БД не трогает) |
| `TicketProcessingService` | Полный сценарий: приём → guard → классификация → персист → `TicketProcessingResult` |

Граф собирает DI-контейнер (`config/container.php`) автовайрингом; контроллеры (CLI и HTTP) получают
`TicketProcessingService` через конструктор.

---

## Quickstart

```bash
cp .env.example .env          # при необходимости поправить порты (см. CLAUDE.md про конфликт 5432)
make up                       # поднять стек; php сам делает composer install + миграции
make test-db                  # создать и мигрировать тестовую БД ticket_test
make test                     # прогнать все сьюты
```

Основной интерфейс — `Makefile` (`make help`). Любая команда выполняется внутри `php`-контейнера.

---

## CLI

Обработать тикет из JSON-файла:

```bash
$ cat ticket.json
{
  "external_id": "EXT-1042",
  "tenant_id": "acme",
  "user_id": "u-77",
  "subject": "Двойное списание за подписку",
  "body": "Сегодня дважды списали за месячную подписку, прошу вернуть одно из списаний.",
  "source": "email"
}

$ make yii args="ticket/process ticket.json"
{
    "ticket_id": 1042,
    "classification_skipped": false,
    "decision": {
        "id": 1042,
        "status": "completed",
        "category": "billing",
        "priority": "high",
        "risk": "money_movement",
        "confidence": 0.91,
        "policy_decision": "requires_approval",
        "final_routing_decision": "human_review",
        "executable_actions_allowed": false,
        "matched_rules": ["risky_category"],
        "model": "fake-classifier",
        "schema_version": "classification.v1",
        "policy_version": "policy.v1",
        "prompt_version": "prompt.v1",
        "trace_id": "trace-9e014540e38d26cb"
    }
}
```

Контракт CLI: **stdout — только результат-JSON**, ошибки — в stderr + код выхода.

| Код выхода | Когда |
|---|---|
| `0` | обработан (даже если решение `failed`) |
| `65` (DATAERR) | битый JSON или отсутствуют обязательные поля |
| `66` (NOINPUT) | файл не найден / недоступен |
| `1` | сбой раннтайма / модели / БД |

Демо-наполнение БД случайными тикетами: `make yii args="test-ticket/add 10"`.

---

## HTTP

`POST ticket/add` с JSON-телом тикета (порт из `HTTP_PORT`, по умолчанию `8080`):

```bash
$ curl -s -X POST 'http://localhost:8080/index.php?r=ticket/add' \
    -H 'Content-Type: application/json' \
    -d '{"external_id":"EXT-1042","tenant_id":"acme","user_id":"u-77",
         "subject":"Двойное списание","body":"Списали дважды.","source":"email"}'
```

| Ситуация | Статус | Тело |
|---|---|---|
| Обработан | `201` | стабильный JSON результата (та же форма, что у CLI) |
| Нет/пустые обязательные поля | `422` | `{"error":"invalid_ticket","details":{"tenant_id":["…cannot be blank."], …}}` |
| Битый JSON в теле | `400` | ошибка парсера (JSON) |
| Сбой раннтайма/модели/БД | `500` | `{"error":"processing_failed"}` |

Форма результата (`TicketProcessingResult::jsonSerialize()`) — единый контракт для CLI и HTTP.

---

## Идемпотентность

Дедуп — на уровне БД (`INSERT … ON CONFLICT DO NOTHING` по уникальному индексу `(tenant_id, external_id)`),
не на TOCTOU-проверке. Поведение сабмита:

| Сценарий | Результат |
|---|---|
| Новый тикет | принят, классифицирован, `classification_skipped: false` |
| Повтор той же команды (решение уже есть) | тот же тикет, **классификатор не вызывается**, возвращается то же решение, `classification_skipped: true` |
| Краш-рекавери: тикет принят, но решение не сохранилось | при повторе **доклассифицируется** (guard смотрит на наличие решения, а не на «новизну» тикета) |

Это зафиксировано тестами: счётчик вызовов классификатора (`calls === 1` на повторе) и счётчики строк в БД
(`tickets` и `ai_decisions` — ровно по одной).

---

## Классификатор и OpenRouter

За `TicketClassifierInterface` стоят две реализации; выбор — в `config/container.php` (плоская привязка,
для OpenRouter — одна строка `=> OpenRouterClassifier::class`):

- **`FakeClassifier`** — детерминированный случайный ответ, прогоняемый через ту же схему-границу. Для демо/CI/локалки без ключа.
- **`OpenRouterClassifier`** — реальный вызов `POST /chat/completions` OpenRouter. Запрос строится из промпта (`messages`) и схемы (`response_format: json_schema, strict: true` + `provider.require_parameters: true` — маршрутизация только к провайдерам с поддержкой structured output). Транспортный сбой → `ClassifierException`; невалидный JSON от модели → `validationErrors` (статус `failed`), не исключение.

Секреты — только в env (`OPENROUTER_API_KEY`), не в репозитории.

### Smoke против живого API

```bash
OPENROUTER_API_KEY=sk-or-... make test args="integration OpenRouterSmokeTest"
```

Проверяет весь путь на реальной модели: запрос → structured output → разбор → валидное решение
(`validationErrors === null`). Без ключа — **скипается** (регулярный прогон не трогает сеть). БД не нужна.

> Этот смок уже отловил реальную несовместимость: один из провайдеров (Amazon Bedrock) отвергал
> `minimum`/`maximum` в JSON-схеме `number` — диапазон `confidence` 0..1 вынесен из исходящей схемы в
> валидацию ответа на нашей стороне.

---

## Версионирование

Каждое решение хранит тройку версий + модель — для воспроизводимости:

- `schema_version` (`classification.v1`) — контракт ответа модели;
- `policy_version` (`policy.v1`) — правила маршрутизации;
- `prompt_version` (`prompt.v1`) — инструкция модели;
- `model` — фактическая модель из ответа (напр. `anthropic/claude-4.5-sonnet-…` или `fake-classifier`).

Енумы домена: `Category`, `Priority`, `Risk`, `RoutingDecision`, `PolicyDecision`, `ClassificationStatus`.

**Политика v1** (детерминированно, поверх классификации):

| Условие | Вердикт | Маршрут |
|---|---|---|
| Ответ модели не прошёл разбор | `blocked` | `manual_triage` |
| Рискованная категория (`destructive_action`, `money_movement`, `external_send`, `security`, `privacy`) | `requires_approval` | `human_review` |
| `confidence < 0.6` | `requires_approval` | `human_review` |
| Иначе | `allowed` | маршрут модели (фолбэк `manual_triage`) |

`executable_actions_allowed = (вердикт == allowed)`.

---

## Ярусы тестов

| Сьют | Покрытие | Зависимости | Запуск |
|---|---|---|---|
| **unit** (53) | домен (схема, политика, классификация, DTO), CLI-контракт; mock / in-memory | без БД (в т.ч. гоняется при недоступном postgres) | `make test args="unit"` |
| **integration** (8) | приём/персист/обработка против реальной БД + OpenRouter smoke | postgres (DB-тесты), ключ+сеть (смок, иначе skip) | `make test args="integration"` |
| **functional** (13) | HTTP-эндпоинт через Yii2 + REST (эмуляция запроса) + дефолтные формы | postgres | `make test args="functional"` |
| **e2e** (2) | `ticket/process` через весь живой стек + проверка реальной записи в БД | postgres | `make test args="e2e"` |

DB-тесты идут в транзакции с откатом (данные не оседают). Unit намеренно DB-free, чтобы прогоняться
в окружении без postgres.

Заметки по тестам: «in-memory AR» в Yii **не** in-memory (`$ar->id = x` лезет в `getTableSchema()` → БД) —
для честных DB-free юнитов в дублях переопределяется `attributes()`. Идемпотентность доказывается прямо
(счётчик вызовов + строки в БД), а не косвенно.

---

## Структура

```
backend/
  controllers/      web: ApiController (JSON+CORS), TicketController (POST ticket/add)
  commands/         console: ticket/process, test-ticket/add (демо-сидер)
  models/
    Entity/         Ticket, AiDecision (ActiveRecord, PostgreSQL, jsonb)
    Enum/           Category, Priority, Risk, RoutingDecision, PolicyDecision, ClassificationStatus
  services/
    Ingestion / Classifiers (+OpenRouter) / Schema / Policy / Prompt / Dto / Exceptions
  config/           web.php, console.php, test.php, container.php (DI), params.php
  migrations/       tickets + ai_decisions
  tests/            unit / integration / functional / e2e
docker-compose.yml, Makefile, .env.example
```

Подробности dev-окружения и команд — в [`CLAUDE.md`](CLAUDE.md).
