# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Что это

AI-сервис классификации тикетов. Репозиторий:

- `backend/` — бэкенд на **Yii2** (шаблон `yiisoft/yii2-app-basic`). Доменной логики тикетов и AI-классификации **ещё нет** — есть дефолтные `SiteController`, формы `LoginForm`/`ContactForm`, in-memory модель `User`. Изменены относительно шаблона только конфиги (переведены на PostgreSQL + Redis, env-driven) и `composer.json`.
- Корень — dev-окружение на docker compose (`docker-compose.yml` + `docker/*`), `.env`, `Makefile`.
- `infra/` — **будет создана** под прод-деплой. Сейчас отсутствует; ничего из корневого dev-compose в прод не переносить как есть.

При работе учитывать: большая часть `backend/` — это boilerplate шаблона, а не осознанные проектные решения. Прежде чем опираться на существующий код как на образец, проверяй, не дефолт ли это. `backend/docker-compose.yml` (apache/mysql, порт 8000) — **устаревший**, заменён корневым compose; не использовать.

## Стек

PHP **8.5**, Yii2 **2.0.55** (констрейнт `~2.0.54`), `yiisoft/yii2-redis` (клиент на `predis`, расширение phpredis не нужно), PostgreSQL **18**, Redis **8**.

Сервисы compose: `nginx` (отдаёт `backend/web`, проксирует PHP на `php:9000`), `php` (php-fpm, образ из `docker/php/Dockerfile`: pdo_pgsql, intl, zip, gd, opcache), `postgres`, `redis`. `./backend` смонтирован в `nginx` и `php` по одному пути `/var/www/html` — это требование FastCGI.

## Команды

Основной интерфейс — **Makefile** (из корня). `make help` — список целей. Любая команда выполняется внутри `php`-контейнера; на хосте Yii/composer напрямую не гоняем.

```bash
make up            # поднять стек; на старте php сам делает composer install + yii migrate
make down          # остановить (volume с данными сохраняется); make destroy — снести с данными
make test          # codeception: все сьюты (нужен поднятый postgres)
make migrate       # применить миграции
make sh            # шелл в работающем php-контейнере
make logs s=php    # логи сервиса
```

Произвольные аргументы — через `args="…"`:

```bash
make composer args="require foo/bar"
make yii args="cache/flush-all"
make migrate-create name=create_ticket_table
make test args="unit"                                  # один сьют
make test args="unit models/UserTest"                  # один класс
make test args="unit models/UserTest:testValidateUser" # один метод
```

Без Makefile то же делается через `docker compose run --rm php <cmd>` (напр. `... php vendor/bin/codecept run`). Makefile дополнительно прокидывает `--user $(id -u)`, чтобы создаваемые файлы (vendor и пр.) не стали root-owned.

Линтера/статанализа в проекте пока нет.

## Архитектура

Стандартный Yii2 MVC. Точки входа: `web/index.php` (HTTP, за nginx→php-fpm) и `yii` (консоль) — обе бутстрапят приложение из конфигов в `config/`.

**Конфигурация раздельная и полностью env-driven** (значения берутся из переменных окружения, прокинутых compose из `.env`; `.env.example` — шаблон):
- `config/web.php` — HTTP-приложение, `config/console.php` — консольное.
- `config/db.php` — PostgreSQL через PDO, host/port/db/user/pass из `DB_*`/`POSTGRES_*`.
- `config/redis.php` — `yii\redis\Connection` (`REDIS_HOST`/`REDIS_PORT`). Подключён в `web.php` и `console.php`; `cache` → `yii\redis\Cache`, `session` → `yii\redis\Session`.
- `cookieValidationKey` берётся из `COOKIE_VALIDATION_KEY`.
- `YII_DEBUG`/`YII_ENV` (задаются в точках входа) переключают поведение: в `YII_ENV_DEV` подключаются **Gii** (`/gii`) и **Debug** (`/debug`) — в проде не должны грузиться.

**Автозапуск php (`docker/php/entrypoint.sh`):** при штатном старте сервиса (CMD `php-fpm`) выполняется `composer install` → ожидание готовности БД → `yii migrate` → запуск fpm. Одноразовые `docker compose run php <cmd>` этот бутстрап **пропускают** (гард по `$1 = php-fpm`), чтобы не тянуть install+migrate на каждый запуск.

**Тесты (Codeception).** `config/test.php` самодостаточен (тесты грузят только его, не `web.php`):
- БД — `config/test_db.php` → отдельная база `ticket_test` (`TEST_DB_NAME`), pgsql. Создать базу: `make test-db`.
- Сессия/кэш в тестах оставлены на дефолтах (файловая сессия, без Redis) — **специально**, чтобы прогон не требовал поднятого Redis. Postgres тестам нужен (юнит/функциональные сьюты используют part `orm`).
- Acceptance-сьют выключен (нужен `tests/acceptance.suite.yml` из `.example`).

## Важные нюансы dev-окружения

- **Конфликт хостового порта БД.** Postgres публикуется как `${POSTGRES_PORT:-5432}:5432`. Если хостовый 5432 занят локальным postgres — задай свободный `POSTGRES_PORT` в `.env`; приложение внутри сети всё равно ходит в `postgres:5432`, хостовый порт нужен только внешним клиентам.
- **composer в entrypoint идёт от root** (`COMPOSER_ALLOW_SUPERUSER=1`). Для ручных одноразовых команд используй `make` (там `--user`), чтобы не плодить root-owned файлы на бинд-маунте.
- `mailer` в dev пишет письма в файл (`useFileTransport`), реально не отправляет.

**Структура `backend/` (Yii2-конвенции):** `controllers/` (web), `commands/` (консольные controllers), `models/`, `views/`, `mail/`, `widgets/`, `assets/`, `web/` (docroot), `migrations/` (создаётся при первой миграции), `runtime/` (генерится, не коммитить).
