#!/bin/bash
set -e

APP_DIR="/opt/apps/agendabot"
cd "$APP_DIR"

echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Deploy iniciado ==="

echo "--- git pull ---"
git pull origin master

echo "--- docker compose build ---"
docker compose build --no-cache app

echo "--- reiniciando containers ---"
docker compose up -d --no-deps app worker scheduler

echo "--- aguardando app subir ---"
sleep 5

echo "--- limpando caches do Laravel ---"
docker exec agendabot-app php artisan config:cache
docker exec agendabot-app php artisan route:cache
docker exec agendabot-app php artisan view:cache

echo "--- migrações pendentes ---"
docker exec agendabot-app php artisan migrate --force

echo "--- removendo imagens antigas ---"
docker image prune -f

echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Deploy concluído ==="
