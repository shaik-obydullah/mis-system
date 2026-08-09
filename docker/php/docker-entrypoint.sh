#!/bin/sh
set -e

echo "[mis] Waiting for MySQL..."
until php -r "new PDO('mysql:host=db;port=3306;dbname=mis_system','mis','REMOVED');" >/dev/null 2>&1; do
  sleep 3
done
echo "[mis] Database ready."

if php bin/console doctrine:schema:create --no-interaction 2>/dev/null; then
  echo "[mis] Schema created. Seeding sample data..."
  php bin/console app:seed --no-interaction
  echo "[mis] Sample data loaded."
fi

exec php-fpm
