#!/usr/bin/env bash
# Deploy para o canivete (inventario.runningwithjoe.com).
# Roda os checks, gera o front, envia o código e aplica no servidor.
# Nunca envia: .env, banco, fotos (storage/app/media), logs, sessões.
set -euo pipefail
cd "$(dirname "$0")/.."

HOST="${DEPLOY_HOST:-root@100.91.35.23}"
DIR="${DEPLOY_DIR:-/var/www/inventario}"
PHP="${PHP:-php8.4}"

$PHP /usr/local/bin/composer check
npm run build

rsync -az --delete \
  --exclude .git --exclude vendor --exclude node_modules --exclude .env \
  --exclude 'database/*.sqlite' --exclude 'database/*.bak-*' \
  --exclude 'storage/logs/*' --exclude 'storage/app/public/*' --exclude 'storage/app/media/*' \
  --exclude 'storage/app/private/*' --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' --exclude 'storage/framework/cache/*' \
  --exclude 'bootstrap/cache/*.php' --exclude public/storage \
  --exclude playwright-report --exclude test-results \
  ./ "$HOST:$DIR/"

ssh "$HOST" "cd $DIR \
  && cp -p database/database.sqlite database/database.sqlite.bak-\$(date +%Y%m%d-%H%M) \
  && COMPOSER_ALLOW_SUPERUSER=1 php8.4 /usr/local/bin/composer install --no-dev -o -n -q \
  && COMPOSER_ALLOW_SUPERUSER=1 php8.4 /usr/local/bin/composer audit --no-dev -n \
  && php8.4 artisan migrate --force \
  && php8.4 artisan optimize \
  && chown -R www-data:www-data . && chown root:www-data .env && chmod 640 .env \
  && systemctl reload php8.4-fpm"

curl -fsS -o /dev/null -w 'login: %{http_code}\n' https://inventario.runningwithjoe.com/login
