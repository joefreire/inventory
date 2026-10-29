#!/usr/bin/env bash
# Servidor dos testes E2E (Playwright sobe ele sozinho).
# Banco proprio (database/e2e.sqlite), recriado a cada execucao, com o usuario
# de teste. A IA fica sem chave: os testes nao gastam Ollama Cloud e o banco do
# dia a dia fica intacto. As variaveis exportadas ganham do .env.
set -euo pipefail
cd "$(dirname "$0")/.."

PHP="${PHP:-php8.4}"
PORT="${E2E_PORT:-8001}"

if [ -f bootstrap/cache/config.php ]; then
  echo "config em cache ignora as variaveis do E2E; rode: $PHP artisan config:clear" >&2
  exit 1
fi

if [ -f public/hot ]; then
  echo "public/hot existe (npm run dev ligado): o E2E testa o build; pare o Vite" >&2
  exit 1
fi

export APP_ENV=testing APP_DEBUG=true APP_URL="http://127.0.0.1:$PORT"
export DB_CONNECTION=sqlite DB_DATABASE="$PWD/database/e2e.sqlite"
export SESSION_DRIVER=file CACHE_STORE=array QUEUE_CONNECTION=database
# fila no banco, sem worker: como em producao, o enriquecimento da importacao fica na fila
# (com sync, a falha da IA sem chave derrubaria a requisicao de upload)
export OLLAMA_API_KEY= NFC_BASE_URL=
export PHP_CLI_SERVER_WORKERS=4

rm -f "$DB_DATABASE"
touch "$DB_DATABASE"
$PHP artisan migrate --force -q
$PHP artisan tinker --execute '
    App\Models\User::create([
        "name" => "E2E",
        "email" => getenv("E2E_EMAIL") ?: "e2e@inventario.test",
        "password" => Illuminate\Support\Facades\Hash::make(getenv("E2E_PASSWORD") ?: "senha-dos-testes-e2e"),
    ]);
'

exec $PHP artisan serve --host=127.0.0.1 --port="$PORT"
