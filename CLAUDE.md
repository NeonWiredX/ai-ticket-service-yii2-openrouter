# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Что это

AI-сервис классификации тикетов. Репозиторий состоит из:

- `backend/` — бэкенд на **Yii2** (шаблон `yiisoft/yii2-app-basic`). Сейчас это **почти неизменённый скелет** шаблона: есть только дефолтные `SiteController`, формы `LoginForm`/`ContactForm` и in-memory модель `User`. Доменной логики тикетов и AI-классификации ещё нет — её предстоит писать.
- `infra/` — **будет создана** под прод-деплой. Сейчас отсутствует. `backend/docker-compose.yml` — это только dev-окружение, не путать с прод-инфрой.

При работе учитывать: многое из того, что встретится в `backend/`, — это boilerplate шаблона, а не осознанные проектные решения. Прежде чем опираться на существующий код как на образец, проверяй, не дефолт ли это.

## Команды

Все команды выполняются из `backend/`.

```bash
composer install                         # зависимости
docker compose up                        # dev-сервер → http://localhost:8000 (Apache+PHP 7.4)
./yii serve                              # альтернатива: встроенный PHP-сервер
./yii <route>                           # консольные команды (миграции, кастомные controllers/)
./yii migrate                           # применить миграции БД
```

### Тесты (Codeception)

```bash
vendor/bin/codecept build                # регенерировать Tester-классы после добавления тестов
vendor/bin/codecept run                  # все сьюты
vendor/bin/codecept run unit             # один сьют (unit / functional / acceptance)
vendor/bin/codecept run unit models/UserTest      # один класс
vendor/bin/codecept run unit models/UserTest:testLogin   # один метод
```

Acceptance-тесты по умолчанию выключены (нужен `tests/acceptance.suite.yml` из `.example`). Тестовая БД и конфиг — `config/test.php` + `config/test_db.php`.

Линтера/статанализа в проекте сейчас нет.

## Архитектура

Стандартный Yii2 MVC. Точки входа: `web/index.php` (HTTP) и `yii` (консоль) — обе бутстрапят приложение из конфигов.

**Конфигурация раздельная и env-зависимая** — это ключевое для понимания:
- `config/web.php` — HTTP-приложение, `config/console.php` — консольное, `config/db.php` — общее подключение к БД, `config/params.php` — параметры приложения.
- Поведение переключается через константы `YII_DEBUG` / `YII_ENV` (задаются в точках входа). В `YII_ENV_DEV` в `web.php` подключаются модули **Gii** (`/gii`, кодогенерация) и **Debug** (`/debug`, тулбар). В проде они не должны грузиться.

**Несоответствие dev-окружения, которое надо разрулить при создании `infra/` и доработке dev:**
- `config/db.php` указывает на `mysql:host=localhost;dbname=yii2basic` с `root`/пустым паролем, но в `docker-compose.yml` **нет сервиса БД** — только PHP. Поднять MySQL-сервис в compose и переключить host на имя сервиса.
- В `config/web.php` захардкожены `cookieValidationKey` и дефолтные значения (`adminEmail` и т.п. в `params.php`). Для прод-`infra/` секреты и окруженческие значения выносить наружу (env / отдельные конфиги), а не править эти файлы под прод.
- `mailer` в dev пишет письма в файл (`useFileTransport`), не отправляет реально.

**Структура `backend/` (Yii2-конвенции):** `controllers/` (web), `commands/` (консольные controllers), `models/`, `views/`, `mail/` (шаблоны писем), `widgets/`, `assets/` (бандлы ассетов через bower/npm-asset), `web/` (docroot), `runtime/` (генерится, не коммитить).
