#!/bin/sh
set -eu

echo "Waiting for MariaDB..."
ATTEMPTS=0
until php -r '
    $host = getenv("DB_HOST") ?: "database";
    $port = getenv("DB_PORT") ?: "3306";
    $db = getenv("DB_DATABASE") ?: "cesizen";
    $user = getenv("DB_USER") ?: "cesizen";
    $pass = getenv("DB_PASSWORD") ?: "cesizen";
    try {
        new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
        exit(0);
    } catch (Throwable $e) {
        exit(1);
    }
'; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -ge 30 ]; then
        echo "MariaDB did not become ready in time."
        exit 1
    fi
    sleep 2
done

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "Starting API..."
exec "$@"
