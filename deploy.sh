#!/bin/bash
set -e

APP_DIR="${APP_DIR:-/opt/apps/agendou}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-master}"
cd "$APP_DIR"

rollback() {
    echo "!!! deploy falhou — restaurando imagem anterior"
    if docker image inspect agendou-app:rollback >/dev/null 2>&1; then
        docker tag agendou-app:rollback agendou-app:latest
        docker compose up -d --no-deps --force-recreate app worker scheduler
    fi
}

trap rollback ERR

echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Deploy iniciado ==="

echo "--- validando configuração do compose ---"
docker compose config --quiet
docker network inspect web >/dev/null
docker network inspect evolution-net >/dev/null

echo "--- git pull ---"
git pull origin "$DEPLOY_BRANCH"

echo "--- docker compose build ---"
if docker image inspect agendou-app:latest >/dev/null 2>&1; then
    docker tag agendou-app:latest agendou-app:rollback
fi
if ! docker compose build app; then
    echo "    build falhou, limpando cache e tentando novamente..."
    docker builder prune -f
    docker compose build --no-cache app
fi

echo "--- parando containers dependentes (worker/scheduler) ---"
docker compose stop worker scheduler 2>/dev/null || true
docker compose rm -f worker scheduler 2>/dev/null || true

echo "--- recriando container app ---"
docker compose up -d --no-deps --force-recreate app

echo "--- aguardando app subir ---"
for i in $(seq 1 12); do
    if docker exec agendou-app curl -fsS http://127.0.0.1/health > /dev/null 2>&1; then
        echo "    app pronto após ${i}x5s"
        break
    fi
    echo "    aguardando... (${i}/12)"
    sleep 5
done

if ! docker exec agendou-app curl -fsS http://127.0.0.1/health >/dev/null; then
    echo "health check falhou"
    exit 1
fi

echo "--- limpando caches do Laravel ---"
docker exec agendou-app php artisan config:cache
docker exec agendou-app php artisan route:cache
docker exec agendou-app php artisan view:cache

echo "--- migrações pendentes ---"
docker exec agendou-app php artisan migrate --force
echo "--- reconfigurando webhooks com autenticação por header ---"
docker exec agendou-app php artisan whatsapp:reconfigure-webhooks

echo "--- subindo worker e scheduler com nova imagem ---"
docker compose up -d --no-deps --force-recreate worker scheduler
for service in agendou-worker agendou-scheduler; do
    if [ "$(docker inspect -f '{{.State.Running}}' "$service" 2>/dev/null || true)" != "true" ]; then
        echo "$service não está em execução"
        exit 1
    fi
done

echo "--- health check final ---"
docker exec agendou-app curl -fsS http://127.0.0.1/health >/dev/null

echo "--- removendo imagens antigas ---"
docker image prune -f

echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Deploy concluído ==="
trap - ERR
