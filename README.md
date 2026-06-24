# AgendaBot

Plataforma SaaS multi-tenant de agendamento via WhatsApp com bot de IA. Estabelecimentos conectam seu WhatsApp Business e passam a receber agendamentos automaticamente — sem que o cliente precise instalar nada.

## Visão geral

- **Cliente final** agenda pelo WhatsApp do estabelecimento em linguagem natural
- **Dono do estabelecimento** configura tudo pelo painel web (profissionais, serviços, horários)
- **Bot** usa Claude API (Haiku 4.5) para conduzir a conversa e criar o agendamento
- **Atendente humano** pode assumir qualquer conversa a qualquer momento
- **Super admin** gerencia todos os tenants, visualiza logs e monitora custo de IA

## Stack

| Camada | Tecnologia |
|--------|-----------|
| Backend | Laravel 11 (PHP 8.3) |
| Frontend | React 18 + TypeScript + Inertia.js |
| Banco | PostgreSQL 16 |
| Fila | Laravel Queue (database driver) |
| WhatsApp | Evolution API |
| IA | Anthropic Claude API — `claude-haiku-4-5` |
| Build | Vite + Tailwind CSS |
| Deploy | Docker Compose + GitHub Actions |

## Funcionalidades

### Painel do estabelecimento
- **Agenda visual** — calendário de disponibilidade por profissional
- **Agendamentos** — listar, cancelar e concluir; criar manualmente com notificação via WhatsApp
- **Profissionais** — cadastro com especialidades e horários de atendimento por dia da semana
- **Serviços** — nome, duração, preço (faixa min/max), flag de avaliação prévia
- **Opções extras** — convênios, formas de pagamento, produtos adicionais
- **Clientes** — histórico de agendamentos e conversas
- **WhatsApp** — conectar instância via QR Code, monitorar status de conexão em tempo real
- **Conversas** — histórico completo do chat; atendente pode assumir/devolver ao bot
- **Configurações do bot** — nome do agente, tom de voz, instruções personalizadas

### Super admin
- Gerenciar tenants (criar, editar, ativar/desativar, impersonar para suporte)
- Visualizador de logs em tempo real com filtro por nível (ERROR, WARNING, INFO, DEBUG)
- Monitoramento de custo da Claude API por empresa: input, output, cache hit rate e economia

### Bot de IA
- Entende linguagem natural para agendar, escolher profissional, data e horário
- Prompt caching habilitado (~90% de desconto em cache hits no bloco estático)
- Transferência automática para humano em casos de insatisfação ou dúvidas fora do escopo
- Atendente pode assumir qualquer chat a qualquer momento — ao enviar uma mensagem o takeover acontece automaticamente

## Arquitetura

```
Cliente WhatsApp
      │
      ▼
Evolution API ──── webhook ────► POST /webhook/{tenant-slug}
                                          │
                                          ▼
                               ProcessarMensagemWhatsapp (Job)
                                          │
                          ┌───────────────┼───────────────┐
                          ▼               ▼               ▼
                  ClaudeAgentService  AgendamentoService  EvolutionApiService
                  (IA + caching)      (criar/validar)     (enviar resposta)
                          │
                          ▼
                    TokenUsage (log de custo por chamada)
```

**Anti double-booking** via constraint de exclusão mútua do PostgreSQL (`btree_gist`):
```sql
EXCLUDE USING gist (profissional_id WITH =, tstzrange(inicio, fim) WITH &&)
WHERE (status != 'cancelado')
```

## Configuração

### Pré-requisitos

- Docker e Docker Compose
- Instância da [Evolution API](https://github.com/EvolutionAPI/evolution-api) acessível
- Chave da [Anthropic API](https://console.anthropic.com/)

### Variáveis de ambiente

Copie `.env.example` para `.env` e preencha:

```env
APP_URL=https://seu-dominio.com

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=agendabot
DB_USERNAME=postgres
DB_PASSWORD=senha-segura

QUEUE_CONNECTION=database

EVOLUTION_API_URL=http://evolution-api:8080
EVOLUTION_API_KEY=sua-chave-global

CLAUDE_API_KEY=sk-ant-...
CLAUDE_MODEL=claude-haiku-4-5-20251001
```

### Subir com Docker

```bash
docker compose up -d

# Primeira vez: migrar banco e criar dados de exemplo
docker exec -it agendabot-app php artisan migrate --seed
```

O seeder cria:
- **Super admin:** `admin@agendabot.com` / `password`
- Dois tenants de exemplo (Barbearia do João e Arena Sports) com profissionais e horários

### Desenvolvimento local

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed

# Inicia Laravel, queue worker, log viewer e Vite simultaneamente
composer run dev
```

## Deploy

O arquivo `.github/workflows/deploy.yml` faz deploy automático via SSH no push para `master`.

**Secrets necessários no GitHub:** `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`

O script `deploy.sh` executa no servidor:
1. `git pull`
2. Build da imagem Docker sem cache
3. Recria o container `app` com `--force-recreate`
4. Aguarda o app subir (health check com `php artisan --version`)
5. Roda migrações pendentes
6. Recria worker e scheduler com a nova imagem
7. Limpa imagens antigas

## Estrutura principal

```
app/
├── Http/Controllers/
│   ├── Tenant/               # Painel do estabelecimento
│   ├── SuperAdmin/           # Painel super admin
│   └── WebhookController.php # Entrada do bot
├── Jobs/
│   └── ProcessarMensagemWhatsapp.php   # Orquestra o fluxo do bot
├── Models/
│   ├── Tenant, Profissional, Servico, Agendamento
│   ├── Conversa, Mensagem, Cliente
│   └── TokenUsage            # Rastreamento de custo da IA
└── Services/
    ├── ClaudeAgentService.php     # Claude API com prompt caching
    ├── AgendamentoService.php     # Criação de agendamentos
    └── EvolutionApiService.php    # WhatsApp via Evolution API

resources/js/Pages/
├── Home.tsx, Precos.tsx           # Landing page pública
├── Onboarding/                    # Fluxo de cadastro
├── Tenant/                        # Painel do estabelecimento
│   ├── Agenda.tsx
│   ├── Conversas/Index.tsx        # Chat com clientes
│   ├── Profissionais/, Servicos/, Agendamentos/
│   └── WhatsApp.tsx
└── SuperAdmin/
    ├── Dashboard.tsx, Logs.tsx
    └── TokenUsage.tsx             # Custo da IA por empresa
```

## Custo de IA

Usando Claude Haiku 4.5 com prompt caching:

| Tipo | Preço |
|------|-------|
| Input | $1,00 / MTok |
| Output | $5,00 / MTok |
| Cache write | $1,25 / MTok |
| Cache read | $0,10 / MTok |

Com cache hit rate de ~70%, o custo estimado é **~$0,0004 por mensagem**. O painel `/superadmin/tokens` mostra o custo real por empresa e a economia gerada pelo cache em tempo real.
