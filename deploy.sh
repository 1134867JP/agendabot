#!/bin/bash
set -e

APP_DIR="${APP_DIR:-/opt/apps/agendabot}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-master}"
cd "$APP_DIR"

remove_compose_service_containers() {
    service="$1"
    container_ids="$(docker ps -aq \
        --filter "label=com.docker.compose.project=agendabot" \
        --filter "label=com.docker.compose.service=$service")"

    if [ -n "$container_ids" ]; then
        echo "    removendo containers antigos do serviço $service"
        docker rm -f $container_ids
    fi
}

rollback() {
    echo "!!! deploy falhou — restaurando imagem anterior"
    if docker image inspect agendabot-app:rollback >/dev/null 2>&1; then
        docker tag agendabot-app:rollback agendabot-app:latest
        remove_compose_service_containers app
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

echo "--- aplicando migrações com a nova imagem antes da troca do app ---"
# Evita iniciar a nova aplicação contra um schema antigo. As migrações do projeto
# devem permanecer retrocompatíveis com a versão anterior para permitir rollback.
docker compose run --rm --no-deps app php artisan migrate --force

echo "--- parando containers dependentes (worker/worker-batch/scheduler) ---"
docker compose stop worker worker-batch scheduler 2>/dev/null || true
docker compose rm -f worker worker-batch scheduler 2>/dev/null || true

echo "--- recriando container app ---"
remove_compose_service_containers app
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

echo "--- criptografando backups legados ---"
docker exec agendabot-app php artisan whatsapp:encrypt-backups
echo "--- rotacionando tokens de webhook expostos ---"
if ! docker exec agendabot-app php artisan security:rotate-webhook-tokens --stale --force; then
    echo "    alguns tokens não foram rotacionados; serão tentados novamente no próximo deploy"
fi
echo "--- reconfigurando webhooks com autenticação por header ---"
docker exec agendabot-app php artisan whatsapp:reconfigure-webhooks

echo "--- verificando integração Evolution API ---"
evolution_check="$(docker exec agendabot-app sh -c '
if [ -z "${EVOLUTION_API_URL:-}" ]; then
    printf "url_ausente"
elif [ -z "${EVOLUTION_API_KEY:-}" ]; then
    printf "chave_ausente"
else
    status=$(curl -sS -o /dev/null -w "%{http_code}" --connect-timeout 5 --max-time 15 --retry 1 --retry-delay 1 --retry-all-errors \
        -H "apikey: ${EVOLUTION_API_KEY}" \
        "${EVOLUTION_API_URL%/}/instance/fetchInstances" 2>/dev/null || true)
    printf "http_%s" "${status:-000}"
fi
')"
case "$evolution_check" in
    http_2*) echo "    Evolution API disponível (${evolution_check#http_})" ;;
    *) echo "    AVISO: Evolution API indisponível (${evolution_check}). A conexão por WhatsApp não funcionará até a integração ser corrigida." ;;
esac

echo "--- subindo workers e scheduler com nova imagem ---"
docker compose up -d --no-deps --force-recreate worker worker-batch scheduler
for service in agendabot-worker agendabot-worker-batch agendabot-scheduler; do
    if [ "$(docker inspect -f '{{.State.Running}}' "$service" 2>/dev/null || true)" != "true" ]; then
        echo "$service não está em execução"
        exit 1
    fi
done

echo "--- health check final ---"
for i in $(seq 1 12); do
    if docker exec agendabot-app curl -fsS http://127.0.0.1/health/ready >/dev/null 2>&1; then
        echo "    workers e scheduler prontos após ${i}x5s"
        break
    fi
    echo "    aguardando workers e scheduler... (${i}/12)"
    sleep 5
done
docker exec agendabot-app curl -fsS http://127.0.0.1/health/ready >/dev/null

echo "--- removendo imagens antigas ---"
docker image prune -f

echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Deploy concluído ==="
trap - ERR
