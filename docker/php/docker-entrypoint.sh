#!/bin/sh
set -e

echo "[mis] Waiting for MySQL..."
until php -r '$u=getenv("DATABASE_URL"); if(!$u){exit(1);} $p=parse_url($u); new PDO(sprintf("mysql:host=%s;port=%s;dbname=%s",$p["host"],$p["port"]??3306,ltrim($p["path"],"/")),$p["user"],$p["pass"]);' >/dev/null 2>&1; do
  sleep 3
done
echo "[mis] Database ready."

if php bin/console doctrine:schema:create --no-interaction 2>/dev/null; then
  echo "[mis] Schema created. Seeding sample data..."
  php bin/console app:seed --no-interaction
  echo "[mis] Sample data loaded."
fi

exec php-fpm
