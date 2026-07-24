#!/bin/bash
set -e

APP_DIR="${APP_DIR:-/opt/apps/agendabot}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-master}"
cd "$APP_DIR"

rollback() {
    echo "!!! deploy falhou — restaurando imagem anterior"
    if docker image inspect agendabot-app:rollback >/dev/null 2>&1; then
        docker tag agendabot-app:rollback agendabot-app:latest
        docker compose up -d --no-deps --force-recreate app worker worker-batch scheduler
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
if docker image inspect agendabot-app:latest >/dev/null 2>&1; then
    docker tag agendabot-app:latest agendabot-app:rollback
fi
if ! docker compose build app; then
    echo "    build falhou, limpando cache e tentando novamente..."
    docker builder prune -f
    docker compose build --no-cache app
fi

echo "--- parando containers dependentes (worker/worker-batch/scheduler) ---"
docker compose stop worker worker-batch scheduler 2>/dev/null || true
docker compose rm -f worker worker-batch scheduler 2>/dev/null || true

echo "--- recriando container app ---"
docker compose up -d --no-deps --force-recreate app

echo "--- ajustando permissões do storage ---"
docker exec -u root agendabot-app sh -c '
mkdir -p /var/www/html/storage/app/private/whatsapp-backups &&
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache &&
chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache
'

echo "--- aguardando app subir ---"
for i in $(seq 1 12); do
    if docker exec agendabot-app curl -fsS http://127.0.0.1/health > /dev/null 2>&1; then
        echo "    app pronto após ${i}x5s"
        break
    fi
    echo "    aguardando... (${i}/12)"
    sleep 5
done

if ! docker exec agendabot-app curl -fsS http://127.0.0.1/health >/dev/null; then
    echo "health check falhou"
    exit 1
fi

echo "--- limpando caches do Laravel ---"
docker exec agendabot-app php artisan config:cache
docker exec agendabot-app php artisan route:cache
docker exec agendabot-app php artisan view:cache

echo "--- migrações pendentes ---"
docker exec agendabot-app php artisan migrate --force
echo "--- criptografando backups legados ---"
docker exec agendabot-app php artisan whatsapp:encrypt-backups
echo "--- rotacionando tokens de webhook expostos ---"
if ! docker exec agendabot-app php artisan security:rotate-webhook-tokens --stale --force; then
    echo "    alguns tokens não foram rotacionados; serão tentados novamente no próximo deploy"
fi
echo "--- reconfigurando webhooks com autenticação por header ---"
docker exec agendabot-app php artisan whatsapp:reconfigure-webhooks

echo "--- subindo workers e scheduler com nova imagem ---"
docker compose up -d --no-deps --force-recreate worker worker-batch scheduler
for service in agendabot-worker agendabot-worker-batch agendabot-scheduler; do
    if [ "$(docker inspect -f '{{.State.Running}}' "$service" 2>/dev/null || true)" != "true" ]; then
        echo "$service não está em execução"
        exit 1
    fi
done

echo "--- health check final ---"
docker exec agendabot-app curl -fsS http://127.0.0.1/health >/dev/null

echo "--- removendo imagens antigas ---"
docker image prune -f

echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Deploy concluído ==="
trap - ERR
