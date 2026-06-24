#!/bin/bash
set -e

APP_DIR="/opt/apps/agendabot"
cd "$APP_DIR"

echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Deploy iniciado ==="

echo "--- git pull ---"
git pull origin master

echo "--- docker compose build ---"
docker compose build app

echo "--- parando containers dependentes (worker/scheduler) ---"
docker compose stop worker scheduler 2>/dev/null || true
docker compose rm -f worker scheduler 2>/dev/null || true

echo "--- recriando container app ---"
docker compose up -d --no-deps --force-recreate app

echo "--- aguardando app subir ---"
for i in $(seq 1 12); do
    if docker exec agendabot-app php artisan --version > /dev/null 2>&1; then
        echo "    app pronto após ${i}x5s"
        break
    fi
    echo "    aguardando... (${i}/12)"
    sleep 5
done

echo "--- limpando caches do Laravel ---"
docker exec agendabot-app php artisan config:cache
docker exec agendabot-app php artisan route:cache
docker exec agendabot-app php artisan view:cache

echo "--- migrações pendentes ---"
docker exec agendabot-app php artisan migrate --force

echo "--- subindo worker e scheduler com nova imagem ---"
docker compose up -d --no-deps --force-recreate worker scheduler

echo "--- removendo imagens antigas ---"
docker image prune -f

echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Deploy concluído ==="
