#!/bin/sh
# Стартовый скрипт php-контейнера: ставит зависимости, ждёт БД, катит миграции,
# затем передаёт управление штатной команде образа (php-fpm).
set -e

export COMPOSER_ALLOW_SUPERUSER=1
cd /var/www/html

# Бутстрап выполняем только при штатном старте сервиса (php-fpm),
# чтобы одноразовые `docker compose run php <cmd>` (composer, codecept, yii ...)
# не тянули за собой install+migrate.
if [ "$1" = "php-fpm" ]; then
    echo "[entrypoint] composer install..."
    composer install --no-interaction --prefer-dist --no-progress

    echo "[entrypoint] ждём готовности БД..."
    i=0
    until php -r '
    try {
        new PDO(
            sprintf("pgsql:host=%s;port=%s;dbname=%s",
                getenv("DB_HOST") ?: "postgres",
                getenv("DB_PORT") ?: "5432",
                getenv("DB_NAME") ?: "ticket"),
            getenv("DB_USER") ?: "ticket",
            getenv("DB_PASSWORD") ?: "ticket"
        );
        exit(0);
    } catch (Throwable $e) {
        exit(1);
    }' 2>/dev/null; do
        i=$((i + 1))
        if [ "$i" -ge 30 ]; then
            echo "[entrypoint] БД недоступна ~60с — продолжаю без ожидания"
            break
        fi
        sleep 2
    done

    echo "[entrypoint] yii migrate..."
    php yii migrate --interactive=0

    echo "[entrypoint] старт php-fpm"
fi

exec docker-php-entrypoint "$@"
